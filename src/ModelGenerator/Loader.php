<?php
/**
 * @file       Loader.php
 * @package    sdk-generator
 * @author     Michael Calcinai <michael@calcin.ai>
 */

declare(strict_types=1);

namespace Wave\SDK\ModelGenerator;

abstract class Loader {

    public static function create(array $config){

        switch($config['type']){
            case 'git':
                return new Loader\Git($config);
            case 'file':
                return new Loader\File($config);
            case 'string':
                return new Loader\StringLoader($config);
            default:
                $type = $config['type'] ?? 'unknown';
                throw new \InvalidArgumentException(sprintf('[%s] is not a supported loader type', $type));
        }


    }

    abstract public function getContent();

}
