<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift;

use CrazyGoat\Forklift\Log\NullLogger;
use Psr\Log\LoggerInterface;

class ForkliftManager
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    private int $workerCount = 2;
    private array $workers = [];

    public function start(): void
    {
        if ($this->isMaster()) {
            $this->startMaster();
        } else {
            $this->startWorker();
        }
    }

    private function isMaster(): bool
    {
        return !isset($_ENV['WORKER_ID']);
    }

    private function startMaster(): void
    {
        $this->logger->info('Starting master process');
        $this->logger->info(sprintf("Worker count: %d", $this->workerCount));

        // Uruchamianie worker procesów
        for ($i = 1; $i <= $this->workerCount; $i++) {
            $this->startWorkerProcess($i);
        }
        $this->setupSignalHandlers();

        // Główna pętla master procesu
        while (true) {
            $this->logger->info('Master process dispatching signals');
            pcntl_signal_dispatch();
            $this->checkWorkers();
            sleep(5);
        }
    }

    private function startWorkerProcess(int $workerId): void
    {
        $pid = pcntl_fork();

        if ($pid === -1) {
            $this->logger->error('Cannot fork worker process');
        } elseif ($pid !== 0) {
            // Master process
            $this->logger->info(sprintf('Started worker process %d', $pid));
            $this->workers[$workerId] = $pid;
            $this->setupSignalHandlers();
        } else {
            $this->logger->info(sprintf('Starting worker process %d', $pid));
            $_ENV['WORKER_ID'] = $workerId;
            $this->startWorker();
            exit(0);
        }
    }

    private function startWorker(): void
    {
        $this->logger->info('Clearing signal handlers for child process');
        pcntl_signal(SIGINT, SIG_DFL);
        pcntl_signal(SIGTERM, SIG_DFL);
        pcntl_signal(SIGCHLD, SIG_IGN);

        $workerId = $_ENV['WORKER_ID'];

        while (true) {
            $this->logger->info(sprintf('Hello world from worker #%d', $workerId));
            pcntl_signal_dispatch();
            sleep(1);
        }

    }

    private function setupSignalHandlers(): void
    {
        $this->logger->info('Setting up signal handlers');
        pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
        pcntl_signal(SIGCHLD, [$this, 'checkWorkers']);
        $this->logger->info('Signal handlers set');
    }

    public function handleShutdown($signal): void
    {
        $this->logger->info(sprintf('Received shutdown signal %d', $signal));

        foreach ($this->workers as $workerId => $pid) {
            $this->logger->info(sprintf('Closing worker #%d (PID: %d)', $workerId, $pid));
            if (posix_kill($pid, SIGTERM) === false) {
                $this->logger->error(sprintf("Cannot kill worker process, error:%s", posix_strerror(posix_get_last_error())));

                continue;
            }
            pcntl_waitpid($pid, $status);
        }

        $this->logger->info('All workers closed');
        exit(0);
    }

    public function checkWorkers(): void
    {
        if ($this->isMaster()) {
            $needRestart = [];
            while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
                $this->logger->warning(sprintf('Worker process %d exited', $pid));
                foreach ($this->workers as $workerId => $workerPid) {
                    if ($pid === $workerPid) {
                        $needRestart[] = $workerId;
                    }
                }
            }

            foreach ($needRestart as $workerId) {
                $this->startWorkerProcess($workerId);
            }
        }
    }
}
