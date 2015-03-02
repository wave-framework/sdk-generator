<?php


namespace Wave\Swagger\Generator\Parser;


use Symfony\Component\Yaml\Yaml;
use Wave\Annotation;
use Wave\Config;
use Wave\Reflector;
use Wave\Router\Action;
use Wave\Router\Generator;
use Wave\Swagger\Generator\Operation;
use Wave\Swagger\Generator\Parameter;
use Wave\Validator;

class FromRoutes extends Parser {

    const INCLUDE_SCHEMA_KEY = 'x-include-schema';

    static $type_translations = array(
        'int' => 'integer',
        'string' => '%s'
    );

    static $allowed_methods = array(
        'get', 'post', 'put', 'delete',
        'options', 'head', 'patch'
    );

    protected $controller_dir;
    protected $schema_dir;
    protected $includes_dir;

    public function __construct($controller_dir, $schema_dir, $includes_dir) {
        $this->controller_dir = $controller_dir;
        $this->schema_dir = $schema_dir;
        $this->includes_dir = $includes_dir;
    }


    public function getOperations() {

        $reflector = new Reflector($this->controller_dir);
        $reflected_options = $reflector->execute();
        $routes = Generator::buildRoutes($reflected_options);

        /**
         * @var string $callable
         * @var Action $action
         */
        $operations = [];

        foreach($routes['default'] as $callable => $action){

            // Retrieve class and method
            list($class, $function) = explode('.', $action->getAction());

            $class = preg_replace('/^Controllers\\\\?/i', '', $class);
            $class = preg_replace('/Controller$/i', '', $class);

            foreach($action->getRoutes() as $route){

                $matches = preg_split('/(?<!<)\/(?!>)/', $route);
                $method = strtolower(array_shift($matches));

                if(!in_array($method, self::$allowed_methods)){
                    continue;
                }

                $in_hint = $this->getParameterInHint($method);

                $operation = new Operation([
                    'method' => $method,
                    'class' => $class,
                    'function' => $function
                ]);

                foreach($action->getAnnotations() as $key => $annotations){
                    foreach($annotations as $annotation){
                        $this->applyAnnotation($key, $annotation, $operation, $in_hint);
                    }
                }

                foreach ($matches as $i => $part) {
                    if (preg_match('/<(?<type>.+?)>(?<name>\w+)/i', $part, $match)) {
                        $matches[$i] = sprintf('{%s}', $match['name']);
                        $parameter = array(
                            'name' => $match['name'],
                            'in' => Parameter::IN_PATH,
                            'required' => true
                        );
                        $this->convertType($match['type'], $parameter);
                        $operation->addParameter(new Parameter($parameter));
                    }
                }

                $route = '/' . implode('/', $matches);

                $operation->path = $route;
                $operation->resolveParameterPlacement();

                if(!array_key_exists($route, $operations))
                    $operations[$route] = array();

                $operations[$route][$method] = $operation;
            }
        }

        return $operations;

    }

    private function applyAnnotation($key, Annotation $annotation, Operation &$operation, $in_hint){

        $fix_case = array(
            'operationid' => 'operationId'
        );

        $key = isset($fix_case[$key]) ? $fix_case[$key] : $key;

        switch($key){
            case 'summary':
            case 'description':
            case 'operationId':
                $operation->$key = $annotation->getValue();
                break;
            case 'deprecated':
                $operation->$key = in_array($annotation->getValue(), array(1, true, '1', 'true'), true);
                break;
            case 'tags':
            case 'consumes':
            case 'produces':
            case 'schemes':
                $operation->$key->merge(array_map(function($v){ return trim($v); }, explode(',', $annotation->getValue())));
                break;
            case 'parameter':
                $operation->addParameter($this->parseParameter($annotation->getValue(), $in_hint));
                break;
            case 'params':
            case 'validate':
                $operation->mergeParameters($this->resolveSchema($annotation->getValue(), $in_hint));

                break;

        }

    }

    /**
     * @param $schema
     * @param string $parameter_in_hint
     * @return array[]
     */
    private function resolveSchema($schema, $parameter_in_hint){
        $schema_file = sprintf('%s%s.php', $this->schema_dir, $schema);
        if(!file_exists($schema_file))
            throw new \RuntimeException("Could not resolve validation schema {$schema}, looked in {$schema_file}");

        $schema = require $schema_file;
        $parameters = [];

        foreach ($schema['fields'] as $key => $val) {
            $parameter = [
                'name' => $key,
                'in' => Parameter::IN_GUESS,
                '_in' => $parameter_in_hint,
                'required' => isset($val['required']) && is_bool($val['required']) ? $val['required'] : false
            ];
            $this->convertType(isset($val['type']) ? $val['type'] : 'string', $parameter);

            if (isset($schema['aliases'][$key])) {
                $parameter['alias'] = $parameter['name'];
                if(is_array($schema['aliases'][$key]))
                    $parameter['name'] = $schema['aliases'][$key][0];
                else
                    $parameter['name'] = $schema['aliases'][$key];
            }

            $parameters[] = new Parameter($parameter);
        }

        return $parameters;
    }

    private function parseParameter($annotation, $parameter_in_hint){

        $data = array(
            'type' => 'string',
            'required' => true
        );

        $parts = explode(' ', $annotation);
        // detect if the type was specified
        if($parts[0][0] !== '$') {
            $this->convertType(array_shift($parts), $data);
        }

        $data['name'] = substr(array_shift($parts), 1);

        if($parts[0] === '[optional]'){
            $data['required'] = false;
            array_shift($parts);
        }

        $description = trim(implode(' ', $parts));
        if(!empty($description))
            $data['description'] = $description;

        return new Parameter($data);

    }

    private function parseIncludeFile($include, $parameter_in_hint) {
        $include_file = sprintf("%s%s.yml", $this->includes_dir, $include);
        if(!file_exists($include_file))
            throw new \RuntimeException("Could not resolve swagger include {$include}, looked in {$include_file}");

        $contents = Yaml::parse(file_get_contents($include_file));

        $operation = new Operation();
        // check for x-includes and things
        if(array_key_exists('parameters', $contents)){
            foreach($contents['parameters'] as $i => $parameter){
                if(array_key_exists(static::INCLUDE_SCHEMA_KEY, $parameter)){
                    $operation->mergeParameters($this->resolveSchema($parameter[static::INCLUDE_SCHEMA_KEY], $parameter_in_hint));
                }
                else {
                    $operation->addParameter(new Parameter($parameter));
                }
            }
        }

        return $operation;
    }

    private function getParameterInHint($method){
        switch($method){
            case 'head':
            case 'options':
            case 'delete':
            case 'get':
                return Parameter::IN_QUERY;
            case 'post':
            case 'put':
            case 'patch':
            default:
                return Parameter::IN_BODY;
        }
    }

    private function convertType($type, array &$parameter){
        switch($type){
            case 'int':
                $parameter['type'] = 'integer';
                return;
            case 'float':
                $parameter['type'] = 'number';
                $parameter['format'] = 'float';
                return;
            case 'bool':
            case 'boolean':
                $parameter['type'] = 'boolean';
                return;
            case 'string':
            case 'email':
                $parameter['type'] = 'string';
                return;
            case 'array':
                $parameter['type'] = 'array';
                return;
            default:
                // regex patterns
                if($type[0] == '/'){
                    $parameter['type'] = 'string';
                    $parameter['pattern'] = substr($type, 1, -1);
                }
                else {
                    trigger_error("Unknown type [{$type}]", E_USER_NOTICE);
                    $parameter['type'] = $type;
                }

                return;
        }
    }



}