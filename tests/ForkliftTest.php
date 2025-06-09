<?php

declare(strict_types=1);

namespace Forklift\Tests;

use CrazyGoat\Forklift\Exception\NotChildProcessException;
use CrazyGoat\Forklift\Forklift;
use PHPUnit\Framework\TestCase;

class ForkliftTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($_ENV[Forklift::FORKLIFT_PROCESS_NUMBER], $_ENV[Forklift::FORKLIFT_CHILD]);
    }

    public function testIsParent(): void
    {
        $this->assertTrue(Forklift::isParent());
    }

    public function testIsChild(): void
    {
        Forklift::setProcessNumber(1);
        $this->assertTrue(Forklift::isChild());
    }

    public function testProcessNumber(): void
    {
        Forklift::setProcessNumber(1);
        $this->assertEquals(1, Forklift::processNumber());
    }

    public function testProcessNumberException(): void
    {
        self::expectException(NotChildProcessException::class);
        Forklift::processNumber();
    }
}
