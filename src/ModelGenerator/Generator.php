<?php

declare(strict_types=1);

namespace Wave\SDK\ModelGenerator;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

class Generator {

    private static array $defaults = [];

    private Schema $input;

    private array $args;

    private Environment $twig;

    public function __construct(Schema $input, array $args = []) {

        $this->input = $input;
        $this->args = array_merge(self::$defaults, $args);

        $loader = new FilesystemLoader(__DIR__ . DIRECTORY_SEPARATOR . 'Templates');
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

    /**
     * @return array<string, array<int, string>>
     */
    public function generate(string $output_directory, string $base_namespace, string $base_model_class): array{

        $schemas = $this->input->getSchemaNames();

        $base_namespace = trim($base_namespace, '\\');
        $output_directory = rtrim($output_directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $files_written = [];
        foreach($schemas as $schema){

            $namespace = $base_namespace . '\\' . ucfirst($schema);
            $directory = $output_directory . ucfirst($schema) . DIRECTORY_SEPARATOR;

            $files = $this->generateModelsFor($schema, $directory, $namespace, $base_model_class);
            $files_written[$schema] = $files;
        }

        return $files_written;

    }

    /**
     * @return array<int, string>
     */
    public function generateModelsFor(string $schema, string $base_directory, string $base_namespace, string $base_model_class): array{

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
                $directory = $base_directory . str_replace('\\', DIRECTORY_SEPARATOR, $ns) . DIRECTORY_SEPARATOR;
            }

            $template_data = [
                'base_model' => $base_model_class,
                'namespace' => $namespace,
                'meta' => $meta,
                'class' => $class,
                'operations' => $class_data['operations']
            ];

            $base_model = $this->twig->render('base-model.phpt', $template_data);
            $base_filename = $directory . 'Base' . DIRECTORY_SEPARATOR . $class . '.php';
            $this->createDirectory(dirname($base_filename));
            $written = file_put_contents($base_filename, $base_model);

            if($written > 0) {
                $files_written[] = $base_filename;
            }

            $stub_filename = $directory . $class . '.php';
            if(!file_exists($stub_filename)){
                $stub_model = $this->twig->render('stub-model.phpt', $template_data);
                $written = file_put_contents($stub_filename, $stub_model);

                if($written > 0) {
                    $files_written[] = $stub_filename;
                }
            }

        }

        return $files_written;

    }

    private function createDirectory(string $directory): bool{
        if(file_exists($directory) && !is_dir($directory)) {
            throw new \RuntimeException("[{$directory}] already exists and is not a directory");
        }
        else if(!is_dir($directory)){
            if(!@mkdir($directory, 0770, true) && !is_dir($directory)) {
                throw new \RuntimeException("Failed to create directory [{$directory}]");
            }
            if(!is_writable($directory)) {
                throw new \RuntimeException("Directory [{$directory}] is not writable");
            }
            return true;
        }

        return true;

    }

}
