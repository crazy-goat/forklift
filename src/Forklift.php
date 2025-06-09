<?php

namespace CrazyGoat\Forklift;

class Forklift
{
    public static function isParent(): bool
    {
        return !self::isChild();
    }

    public static function isChild(): bool
    {
        return isset($_ENV['FORKLIFT_CHILD']) && $_ENV['FORKLIFT_CHILD'] === '1';
    }

    public static function processNumber(): int
    {
        return (int) ($_ENV['FORKLIFT_PROCESS_NUMBER'] ?? 0);
    }
}