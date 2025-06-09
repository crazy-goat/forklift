<?php

namespace CrazyGoat\Forklift;

use CrazyGoat\Forklift\Exception\ForkCreatedException;

class ProcessGroup
{
    private array $pidMap = [];

    /** @var callable */
    private $callback;

    public function __construct(private string $name, private int $size, callable $callback)
    {
        $this->callback = $callback;
    }

    public function run(): void
    {
        foreach (range(1, $this->size) as $processNumber) {
            $fork = new Process($processNumber - 1, $this->callback);
            try {
                $pid = $fork->run();
                if ($pid !== null) {
                    $this->pidMap[$pid] = $fork;
                }
            } catch (ForkCreatedException $exception) {
                continue;
            }
        }
    }
}