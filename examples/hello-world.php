<?php

use CrazyGoat\Forklift\Forklift;
use CrazyGoat\Forklift\ForkliftManager;
use CrazyGoat\Forklift\Log\ConsoleLogger;
use CrazyGoat\Forklift\ProcessGroup;

include __DIR__ . '/../vendor/autoload.php';

$logger = new ConsoleLogger();

$callable = function () use ($logger) {
    pcntl_signal(SIGINT, SIG_DFL);
    pcntl_signal(SIGTERM, SIG_DFL);
    pcntl_signal(SIGCHLD, SIG_IGN);

    while (true) {
        $logger->info(sprintf('Hello world from worker #%d', Forklift::processNumber()));
        pcntl_signal_dispatch();
        sleep(1);
    }
};

$manager = new ForkliftManager($logger, new ProcessGroup('hello world', 2, $callable));
$manager->start();