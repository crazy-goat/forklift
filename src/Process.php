<?php

namespace CrazyGoat\Forklift;

use CrazyGoat\Forklift\Exception\ForkCreatedException;

class Process
{
    /** @var callable */
    private $callback;

    public function __construct(private int $processNumber, $callback)
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
            throw new ForkCreatedException('Cannot fork process');
        } elseif ($pid !== 0) {
            // Parent process
            return $pid;
        } else {
            // Child process
            ($this->callback)($this->processNumber);
            return null;
        }
    }
}