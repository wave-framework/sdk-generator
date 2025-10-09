<?php
/**
 * @package    sdk-generator
 * @author     Michael Calcinai <michael@calcin.ai>
 */

declare(strict_types=1);

namespace Wave\SDK\ModelGenerator\Loader;

use Wave\SDK\ModelGenerator\Loader;

class File extends Loader {

    private string $file;

    /**
     * @param array{file: string} $config
     */
    public function __construct(array $config) {
        if(empty($config['file'])){
            throw new \InvalidArgumentException('File loader configuration requires a "file" key');
        }

        $this->file = $config['file'];
    }

    public function getContent(): string{
        $content = @file_get_contents($this->file);
        if($content === false){
            throw new \RuntimeException(sprintf('Failed to read file [%s]', $this->file));
        }

        return $content;
    }
}
