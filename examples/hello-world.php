<?php

use CrazyGoat\Forklift\ForkliftManager;
use CrazyGoat\Forklift\Log\ConsoleLogger;

include __DIR__ . '/../vendor/autoload.php';

$manager = new ForkliftManager(new ConsoleLogger());
$manager->start();