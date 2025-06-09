<?php

class MasterProcess {
    private $childPids = [];
    private $shouldRestart = true;

    public function __construct() {
        // Set up signal handlers for master

        pcntl_signal(SIGTERM, function ($signal) {
            var_dump($signal);
            exit(0);
        });
//        pcntl_signal(SIGINT, [$this, 'handleShutdown']);

//        register_shutdown_function([$this, 'cleanupChildren']);
    }

    public function startWorker() {
        $pid = pcntl_fork();

        if ($pid == -1) {
            die('Could not fork');
        } elseif ($pid) {
            // Master process
            $this->childPids[$pid] = time();
            echo "Master: Started child with PID $pid\n";
            $this->showCurrentPids();
        } else {
            // Child process - COMPLETELY reset signal handling
            $this->runChildProcess();
        }
    }

    private function runChildProcess() {
        echo "Child " . getmypid() . ": Starting work (handlers configured)\n";

        // Main child work loop with proper signal dispatching
        $counter = 0;
        while (true) {
            // CRITICAL: Dispatch signals regularly
            pcntl_signal_dispatch();

            sleep(1);
        }
    }

    public function checkWorkers($signo) {
        while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
            if (isset($this->childPids[$pid])) {
                unset($this->childPids[$pid]);

                if ($this->shouldRestart) {
                    if (pcntl_wifexited($status)) {
                        $exitCode = pcntl_wexitstatus($status);
                        echo "Master: Child $pid exited with code $exitCode, restarting...\n";
                    } elseif (pcntl_wifsignaled($status)) {
                        $signal = pcntl_wtermsig($status);
                        echo "Master: Child $pid killed by signal $signal, restarting...\n";
                    }

                    // Small delay to ensure clean restart
                    usleep(100000); // 0.1 second
                    $this->startWorker();
                } else {
                    echo "Master: Child $pid terminated during shutdown\n";
                }
            }
        }
    }

    public function handleShutdown($signo) {
        echo "\nMaster: Received shutdown signal $signo\n";
        $this->shouldRestart = false;
        $this->cleanupChildren();
        echo "Master: Shutdown complete\n";
        exit(0);
    }

    public function cleanupChildren() {
        if (empty($this->childPids)) {
            return;
        }

        echo "Master: Shutting down. Terminating child processes...\n";

        foreach ($this->childPids as $pid => $startTime) {
            echo "Master: Sending SIGTERM to child $pid\n";
            if (!posix_kill($pid, SIGTERM)) {
                echo "Master: Failed to send SIGTERM to $pid\n";
            }
        }

        // Wait for graceful shutdown
        $timeout = 5;
        $start = time();
        while (!empty($this->childPids) && (time() - $start) < $timeout) {
            sleep(1);
            pcntl_signal_dispatch();
        }

        // Force kill remaining
        foreach ($this->childPids as $pid => $startTime) {
            echo "Master: Force killing child $pid\n";
            posix_kill($pid, SIGKILL);
            pcntl_waitpid($pid, $status);
        }

        $this->childPids = [];
    }

    private function showCurrentPids() {
        $pids = array_keys($this->childPids);
        echo "Master: Active children: " . implode(', ', $pids) . "\n";
    }

    public function run() {
        echo "=== Master Process (PID: " . getmypid() . ") ===\n";
        echo "Starting 2 worker processes...\n";
        echo "Use 'kill -TERM <child_pid>' to test restart functionality\n";
        echo "Use 'kill -TERM " . getmypid() . "' or Ctrl+C to shutdown all\n\n";

        // Start workers
        $this->startWorker();
        $this->startWorker();

        $counter = 0;
        while ($this->shouldRestart) {
            sleep(1);
            $counter++;

            // Dispatch signals for master
            pcntl_signal_dispatch();

            // Status update every 15 seconds
            if ($counter % 15 == 0) {
                echo "\n--- Status Update ---\n";
                echo "Master uptime: $counter seconds\n";
                $this->showCurrentPids();
                echo "--- End Status ---\n";
            } else {
                echo ".";
                $this->checkWorkers(1);
            }
        }
    }
}

echo "Testing signal handling in forked processes...\n\n";
$master = new MasterProcess();
$master->run();