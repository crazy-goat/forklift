<?php

require_once __DIR__ . '/vendor/autoload.php';

use React\EventLoop\Loop;
use React\Socket\LimitingServer;
use React\Socket\SocketServer;
use React\Http\HttpServer;
use React\Http\Message\Response;
use Psr\Http\Message\ServerRequestInterface;

class MasterWorkerServer
{
    private $workerCount = 8;
    private $port = 1234;
    private $workers = [];
    private bool $forceClose = false;

    public function start()
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

    private function startMaster()
    {
        echo "🚀 Uruchamianie Master procesu...\n";
        echo "📊 Liczba worker procesów: {$this->workerCount}\n";
        echo "🌐 Port: {$this->port}\n";
        echo "🔧 Używa SO_REUSEPORT: Tak\n\n";


        // Uruchamianie worker procesów
        for ($i = 1; $i <= $this->workerCount; $i++) {
            $this->startWorkerProcess($i);
        }
        $this->setupSignalHandlers();

        echo "✅ Master proces uruchomiony. Worker procesy działają.\n";
        echo "🌍 Serwer dostępny pod: http://localhost:{$this->port}\n";
        echo "📝 Naciśnij Ctrl+C aby zatrzymać serwer\n\n";

        // Główna pętla master procesu
        while (true) {
            echo ".";
            pcntl_signal_dispatch();
            $this->checkWorkers();
            sleep(1);
        }
    }

    private function startWorkerProcess(int $workerId)
    {
        $pid = pcntl_fork();

        if ($pid == -1) {
            die("❌ Nie można utworzyć procesu worker\n");
        } elseif ($pid) {
            $this->workers[$workerId] = $pid;
            echo "Master: Started child with PID $pid\n";
            $this->setupSignalHandlers();
        } else {
            // Proces dziecka (worker)
            echo "✨ Worker #{$workerId} uruchomiony (PID: " . getmypid() . ")\n";
            $_ENV['WORKER_ID'] = $workerId;
            $this->startWorker();
            exit(0);
        }
    }

    private function startWorker()
    {
        pcntl_signal(SIGINT, SIG_DFL);
        pcntl_signal(SIGTERM, SIG_DFL);
        pcntl_signal(SIGCHLD, SIG_IGN);

        $workerId = $_ENV['WORKER_ID'];
        $loop = Loop::get();

        // Konfiguracja socketu z SO_REUSEPORT
        $socket = new LimitingServer(new SocketServer("0.0.0.0:{$this->port}", [
            'tcp' => [
                'so_reuseport' => true,
                'backlog' => 10000
            ]
        ], $loop), 10000);

        // Tworzenie HTTP servera
        $http = new HttpServer($loop, function (ServerRequestInterface $request) use ($workerId) {
            $headers = ['Content-Type' => 'text/plain'];
            if ($this->forceClose) {
                $headers['Connection'] = 'close';
            }

            return new Response(
                200,
                $headers,
                'Hello World!'
            );
        });

        // Podłączenie HTTP servera do socketu
        $http->listen($socket);

        echo "🟢 Worker #{$workerId} (PID: " . getmypid() . ") nasłuchuje na porcie {$this->port}\n";

        // Graceful shutdown dla worker
        $loop->addSignal(SIGTERM, function () use ($http, $socket, $loop, $workerId) {
            echo "🛑 Worker #{$workerId} otrzymał sygnał SIGTERM, zamykanie.";
            $this->forceClose = true;
            $socket->close();

            $loop->addPeriodicTimer(5, function () use ($socket, $loop) {
                echo ".";
                if (count($socket->getConnections()) === 0) {
                    $loop->stop();
                    echo "\nZakończono wszystkie połączenia\n";
                }
            });

        });
        $loop->run();
    }

    private function setupSignalHandlers()
    {
        pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
        pcntl_signal(SIGCHLD, [$this, 'checkWorkers']);
    }

    public function handleShutdown($signal)
    {
        echo "\n🛑 Otrzymano sygnał zamknięcia ($signal). Zamykanie worker procesów...\n";

        foreach ($this->workers as $workerId => $pid) {
            echo "⏹️  Zamykanie Worker #{$workerId} (PID: $pid)\n";
            if (posix_kill($pid, SIGTERM) === false) {
                echo 'Your error returned was ' . posix_strerror(posix_get_last_error());
                continue;
            };
            pcntl_waitpid($pid, $status);
        }

        echo "✅ Wszystkie worker procesy zostały zamknięte. Master proces kończy pracę.\n";
        exit(0);
    }

    public function checkWorkers()
    {
        if ($this->isMaster()) {
            $needRestart = [];
            while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
                echo "Proces potomny o PID " . $pid . " zakończył działanie.\n";
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

// Sprawdzenie dostępności wymaganych rozszerzeń
if (!extension_loaded('pcntl')) {
    die("❌ Rozszerzenie PCNTL jest wymagane do uruchomienia master-worker architektury\n");
}

if (!extension_loaded('posix')) {
    die("❌ Rozszerzenie POSIX jest wymagane do zarządzania procesami\n");
}

// Uruchomienie serwera
$server = new MasterWorkerServer();
$server->start();