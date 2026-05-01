<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Tests\Server\Types;

use CrazyGoat\Forklift\Server\Types\ProxyType;
use PHPUnit\Framework\TestCase;

class ProxyTypeTest extends TestCase
{
    public function testCasesHaveExpectedValues(): void
    {
        $this->assertSame('reuse_port', ProxyType::REUSE_PORT->value);
        $this->assertSame('fork_shared', ProxyType::FORK_SHARED->value);
        $this->assertSame('master', ProxyType::MASTER->value);
    }

    public function testAllCasesAreCovered(): void
    {
        $cases = ProxyType::cases();
        $this->assertCount(3, $cases);
        $this->assertContains(ProxyType::REUSE_PORT, $cases);
        $this->assertContains(ProxyType::FORK_SHARED, $cases);
        $this->assertContains(ProxyType::MASTER, $cases);
    }

    public function testFromStringValue(): void
    {
        $this->assertEquals(ProxyType::REUSE_PORT, ProxyType::from('reuse_port'));
        $this->assertEquals(ProxyType::FORK_SHARED, ProxyType::from('fork_shared'));
        $this->assertEquals(ProxyType::MASTER, ProxyType::from('master'));
    }
}
