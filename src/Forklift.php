<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift;

use CrazyGoat\Forklift\Exception\NotChildProcessException;

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

    /**
     * @throws NotChildProcessException
     */
    public static function processNumber(): int
    {
        if (!self::isChild() || !isset($_ENV['FORKLIFT_PROCESS_NUMBER'])) {
            throw new NotChildProcessException();
        }

        return intval($_ENV['FORKLIFT_PROCESS_NUMBER']);
    }
}
