<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Tests\Server\Types;

use CrazyGoat\Forklift\Server\Types\ConfigKeys;
use PHPUnit\Framework\TestCase;

class ConfigKeysTest extends TestCase
{
    public function testAllConstantsAreDefined(): void
    {
        $reflection = new \ReflectionClass(ConfigKeys::class);
        $constants = $reflection->getConstants();

        $expected = [
            'GROUPS',
            'LISTENERS',
            'NAME',
            'SIZE',
            'PORT',
            'PROTOCOL',
            'PROXY',
            'GROUP',
            'HANDLER',
            'TCP_NODELAY',
            'MAX_HEADER_SIZE',
            'MAX_BODY_SIZE',
            'MAX_REQUESTS',
            'MAX_LIFETIME',
            'MEMORY_LIMIT',
            'CONNECTION_TIMEOUT',
            'STATS',
            'STATS_KEY',
        ];

        $this->assertCount(count($expected), $constants);
        foreach ($expected as $name) {
            $this->assertArrayHasKey($name, $constants);
        }
    }

    public function testConstantValues(): void
    {
        $this->assertSame('groups', ConfigKeys::GROUPS);
        $this->assertSame('listeners', ConfigKeys::LISTENERS);
        $this->assertSame('name', ConfigKeys::NAME);
        $this->assertSame('size', ConfigKeys::SIZE);
        $this->assertSame('port', ConfigKeys::PORT);
        $this->assertSame('protocol', ConfigKeys::PROTOCOL);
        $this->assertSame('proxy', ConfigKeys::PROXY);
        $this->assertSame('group', ConfigKeys::GROUP);
        $this->assertSame('handler', ConfigKeys::HANDLER);
        $this->assertSame('tcp_nodelay', ConfigKeys::TCP_NODELAY);
        $this->assertSame('max_header_size', ConfigKeys::MAX_HEADER_SIZE);
        $this->assertSame('max_body_size', ConfigKeys::MAX_BODY_SIZE);
        $this->assertSame('max_requests', ConfigKeys::MAX_REQUESTS);
        $this->assertSame('max_lifetime', ConfigKeys::MAX_LIFETIME);
        $this->assertSame('memory_limit', ConfigKeys::MEMORY_LIMIT);
        $this->assertSame('connection_timeout', ConfigKeys::CONNECTION_TIMEOUT);
        $this->assertSame('stats', ConfigKeys::STATS);
        $this->assertSame('stats_key', ConfigKeys::STATS_KEY);
    }
}
