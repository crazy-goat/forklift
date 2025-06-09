<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Log;

use Psr\Log\LoggerInterface;

class ConsoleLogger implements LoggerInterface
{
    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $path = 'php://stdout';
        if (in_array($level, ['warning', 'error', 'critical', 'alert', 'emergency'], true)) {
            $path = 'php://stderr';
        }

        $contextString = trim(str_replace(["\n", "\r", "\t"], '', var_export($context, true)));

        file_put_contents(
            $path,
            sprintf("%s: %s: %s. Context: %s\n", date('Y-m-d H:i:s.u'), $level, $message, $contextString),
            FILE_APPEND,
        );
    }
}
