<?php
/**
 * @package    sdk-generator
 * @author     Michael Calcinai <michael@calcin.ai>
 */

declare(strict_types=1);

namespace Wave\SDK\ModelGenerator\Loader;

use Wave\SDK\ModelGenerator\Loader;

class Git extends Loader {

    private string $repository;
    private string $file;

    /**
     * @param array{repository: string, file: string} $config
     */
    public function __construct(array $config) {
        if(empty($config['repository']) || empty($config['file'])){
            throw new \InvalidArgumentException('Git loader configuration requires both "repository" and "file" keys');
        }

        $this->repository = $config['repository'];
        $this->file = $config['file'];
    }

    public function getContent(): string{

        $command = sprintf(
            'git archive --remote=%s HEAD %s | tar -xO',
            escapeshellarg($this->repository),
            escapeshellarg($this->file)
        );

        $result = shell_exec($command);

        if($result === null){
            throw new \RuntimeException(sprintf('Failed to retrieve [%s] from repository [%s]', $this->file, $this->repository));
        }

        return trim($result);
    }
}
