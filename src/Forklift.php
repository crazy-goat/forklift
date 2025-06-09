<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift;

use CrazyGoat\Forklift\Exception\NotChildProcessException;

class Forklift
{
    public const FORKLIFT_CHILD = 'FORKLIFT_CHILD';
    public const FORKLIFT_PROCESS_NUMBER = 'FORKLIFT_PROCESS_NUMBER';

    public static function setProcessNumber(int $processNumber): void
    {
        $_ENV[self::FORKLIFT_PROCESS_NUMBER] = $processNumber;
        $_ENV[self::FORKLIFT_CHILD] = '1';
    }

    public static function isParent(): bool
    {
        return !self::isChild();
    }

    public static function isChild(): bool
    {
        return isset($_ENV[self::FORKLIFT_CHILD]) && $_ENV[self::FORKLIFT_CHILD] === '1';
    }

    /**
     * @throws NotChildProcessException
     */
    public static function processNumber(): int
    {
        if (!self::isChild() || !isset($_ENV[self::FORKLIFT_PROCESS_NUMBER])) {
            throw new NotChildProcessException();
        }

        if (is_scalar($_ENV[self::FORKLIFT_PROCESS_NUMBER])) {
            return intval($_ENV[self::FORKLIFT_PROCESS_NUMBER]);
        }

        throw new \InvalidArgumentException('Invalid process number');
    }
}
