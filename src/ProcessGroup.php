<?php

namespace CrazyGoat\Forklift;

use CrazyGoat\Forklift\Exception\ForkCreatedException;
use CrazyGoat\Forklift\Log\NullLogger;
use Psr\Log\LoggerInterface;

class ProcessGroup
{
    private array $pidMap = [];

    /** @var callable */
    private $callback;
    private LoggerInterface $logger;

    public function __construct(private string $name, private int $size, callable $callback)
    {
        $this->callback = $callback;
        $this->logger = new NullLogger();
    }

    public function run(): void
    {
        foreach (range(1, $this->size) as $processNumber) {
            $this->logger->info(sprintf('Starting process %d, from group %s', $processNumber, $this->name));
            $fork = new Process($processNumber - 1, $this->callback);
            try {
                $pid = $fork->run();
                if ($pid !== null) {
                    $this->logger->info(sprintf('Process %d from group %s started, PID: %d', $processNumber, $this->name, $pid));
                    $this->pidMap[$pid] = $fork;
                }
            } catch (ForkCreatedException $exception) {
                $this->logger->error($exception->getMessage());
                continue;
            }
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function withLogger(LoggerInterface $logger): ProcessGroup
    {
        $this->logger = $logger;
        return $this;
    }
}