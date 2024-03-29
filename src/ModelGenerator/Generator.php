<?php


namespace Wave\SDK\ModelGenerator;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

class Generator {

    private static $defaults = [];

    private $twig;

    public function __construct(Schema $input, array $args = []) {

        $this->input = $input;
        $this->args = array_merge(self::$defaults, $args);

        $loader = new FilesystemLoader(__DIR__ . DS . 'Templates');
        $this->twig = new Environment($loader, array(
            'autoescape' => false,
            'debug' => true
        ));

        $this->twig->addFilter(new TwigFilter('var_export', function($arg, $indent = 0, $indent_string = '    '){
            $output = var_export($arg, true);

            $output = str_replace('  ', $indent_string, $output);

            return ltrim(preg_replace('/^/m', str_repeat($indent_string, $indent), $output));

        }));
        $this->twig->addFilter(new TwigFilter('print_r', 'print_r'));
        $this->twig->addFilter(new TwigFilter('explode', function($a, $d){ 
            if ($a === null) {
                return [""];
            }

            return explode($d, $a); 
        }));
    }

    public function generate($output_directory, $base_namespace, $base_model_class){

        $schemas = $this->input->getSchemaNames();

        $base_namespace = trim($base_namespace, '\\');
        $output_directory = rtrim($output_directory, '/') . '/';

        $files_written = [];
        foreach($schemas as $schema){

            $namespace = $base_namespace . '\\' . ucfirst($schema);
            $directory = $output_directory . ucfirst($schema) . DS;

            $files = $this->generateModelsFor($schema, $directory, $namespace, $base_model_class);
            $files_written[$schema] = $files;
        }

        return $files_written;

    }

    public function generateModelsFor($schema, $base_directory, $base_namespace, $base_model_class){

        $models = $this->input->getModels($schema);
        $meta = $this->input->getMeta($schema);
        $files_written = [];

        foreach($models as $class => $class_data){

            $namespace = $base_namespace;
            $directory = $base_directory;

            if(($split = strrpos($class, '\\')) !== false){
                $ns = substr($class, 0, $split);
                $class = substr($class, $split + 1);
                $namespace = $base_namespace . '\\' . $ns;
                $directory = $base_directory . str_replace('\\', '/', $ns) . DS;
            }

            $template_data = [
                'base_model' => $base_model_class,
                'namespace' => $namespace,
                'meta' => $meta,
                'class' => $class,
                'operations' => $class_data['operations']
            ];

            $base_model = $this->twig->render('base-model.phpt', $template_data);
            $base_filename = sprintf('%sBase/%s.php', $directory, $class);
            $this->createDirectory(dirname($base_filename));
            $written = file_put_contents($base_filename, $base_model);

            if($written > 0)
                $files_written[] = $base_filename;

            $stub_filename = sprintf('%s%s.php', $directory, $class);
            if(!file_exists($stub_filename)){
                $stub_model = $this->twig->render('stub-model.phpt', $template_data);
                $written = file_put_contents($stub_filename, $stub_model);

                if($written > 0)
                    $files_written[] = $stub_filename;
            }

        }

        return $files_written;

    }

    private function createDirectory($directory){
        if(file_exists($directory) && !is_dir($directory)) {
            throw new \RuntimeException("[{$directory}] already exists and is not a directory");
        }
        else if(!is_dir($directory)){
            @mkdir($directory, 0770, true);
            if(!is_dir($directory) || !is_writable($directory)) {
                throw new \RuntimeException("Failed to create directory [{$directory}]");
            }
            return true;
        }

        return true;

    }

}
