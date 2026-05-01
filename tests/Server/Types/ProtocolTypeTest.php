<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Tests\Server\Types;

use CrazyGoat\Forklift\Server\Types\ProtocolType;
use PHPUnit\Framework\TestCase;

class ProtocolTypeTest extends TestCase
{
    public function testCasesHaveExpectedValues(): void
    {
        $this->assertSame('tcp', ProtocolType::TCP->value);
        $this->assertSame('http', ProtocolType::HTTP->value);
        $this->assertSame('websocket', ProtocolType::WEBSOCKET->value);
    }

    public function testAllCasesAreCovered(): void
    {
        $cases = ProtocolType::cases();
        $this->assertCount(3, $cases);
        $this->assertContains(ProtocolType::TCP, $cases);
        $this->assertContains(ProtocolType::HTTP, $cases);
        $this->assertContains(ProtocolType::WEBSOCKET, $cases);
    }

    public function testFromStringValue(): void
    {
        $this->assertEquals(ProtocolType::TCP, ProtocolType::from('tcp'));
        $this->assertEquals(ProtocolType::HTTP, ProtocolType::from('http'));
        $this->assertEquals(ProtocolType::WEBSOCKET, ProtocolType::from('websocket'));
    }
}
