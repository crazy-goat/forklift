<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift;

use CrazyGoat\Forklift\Exception\ForkCreatedException;

class Process
{
    /** @var callable */
    private $callback;

    public function __construct(private int $processNumber, callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @throws ForkCreatedException
     */
    public function run(): ?int
    {
        $pid = pcntl_fork();

        if ($pid === -1) {
            $message = pcntl_strerror(pcntl_get_last_error());
            throw new ForkCreatedException(sprintf("Cannot fork process: %s", $message));
        } elseif ($pid !== 0) {
            // Parent process
            return $pid;
        } else {
            // Child process
            $_ENV['FORKLIFT_PROCESS_NUMBER'] = $this->processNumber;
            $_ENV['FORKLIFT_CHILD'] = '1';
            ($this->callback)($this->processNumber);
            return null;
        }
    }

    public function processNumber(): int
    {
        return $this->processNumber;
    }
}
