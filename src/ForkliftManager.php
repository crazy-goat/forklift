<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift;

use CrazyGoat\Forklift\Exception\NotParentProcessException;
use CrazyGoat\Forklift\Log\NullLogger;
use JetBrains\PhpStorm\NoReturn;
use Psr\Log\LoggerInterface;

class ForkliftManager
{
    private LoggerInterface $logger;
    /** @var ProcessGroup[] */
    private array $processGroups;

    public function __construct(LoggerInterface $logger = null, ProcessGroup ...$processGroups)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->processGroups = $processGroups;
    }

    /**
     * @throws NotParentProcessException
     */
    public function start(): void
    {
        if (!Forklift::isParent()) {
            throw new NotParentProcessException('Cannot start worker in child process');
        }

        $this->logger->info('Starting master process');
        $this->logger->info('Found total of ' . count($this->processGroups) . ' process groups');;

        foreach ($this->processGroups as $processGroup) {
            $processGroup->withLogger($this->logger);
            $processGroup->run();
        }
        $this->setupSignalHandlers();

        while (true) {
            $this->logger->info('Master process dispatching signals');
            pcntl_signal_dispatch();
            $this->checkWorkers();
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

    #[NoReturn]
    public function handleShutdown($signal): void
    {
        $this->logger->info(sprintf('Received shutdown signal %d', $signal));

        foreach ($this->processGroups as $processGroup) {
            $processGroup->shutdown();
        }

        $this->logger->info('All workers closed');
        exit(0);
    }

    public function checkWorkers(): void
    {
        if (Forklift::isParent()) {
            $needRestart = [];
            while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
                $this->logger->warning(sprintf('Worker process %d exited', $pid));
                $needRestart[] = $pid;
            }

            foreach ($needRestart as $pid) {
                foreach ($this->processGroups as $processGroup) {
                    $processGroup->restartProcessByPid($pid);
                }
            }
        }
    }
}
