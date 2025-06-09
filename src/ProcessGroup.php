<?php

namespace CrazyGoat\Forklift;

use CrazyGoat\Forklift\Exception\ForkCreatedException;
use CrazyGoat\Forklift\Log\NullLogger;
use Psr\Log\LoggerInterface;

class ProcessGroup
{
    /**
     * @var array<int, Process>
     */
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
            try {
                $this->doFork($processNumber - 1);
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

    public function shutdown(): void
    {
        foreach ($this->pidMap as $pid => $fork) {
            $this->logger->info(sprintf('Closing worker from %s, number: #%d (PID: %d)', $this->name, $fork->processNumber(), $pid));
            if (posix_kill($pid, SIGTERM) === false) {
                $this->logger->error(sprintf("Cannot kill worker process, error:%s", posix_strerror(posix_get_last_error())));

                continue;
            }
            pcntl_waitpid($pid, $status);
            $this->logger->info(sprintf('Worker from %s, number: #%d (PID: %d) closed', $this->name, $fork->processNumber(), $pid));
        }
    }

    public function restartProcessByPid(mixed $pid): void
    {
        if (isset($this->pidMap[$pid])) {
            try {
                $process = $this->pidMap[$pid];
                unset($this->pidMap[$pid]);
                $this->doFork($process->processNumber());
            } catch (ForkCreatedException $exception) {
                $this->logger->error($exception->getMessage());
            }
        }
    }

    /**
     * @throws ForkCreatedException
     */
    private function doFork(int $processNumber): void
    {
        $fork = new Process($processNumber, $this->callback);
        $pid = $fork->run();
        if ($pid !== null) {
            $this->logger->info(sprintf('Process %d from group %s started, PID: %d', $processNumber, $this->name, $pid));
            $this->pidMap[$pid] = $fork;
        }
    }
}