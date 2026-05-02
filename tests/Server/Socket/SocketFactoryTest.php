<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Tests\Server\Socket;

use CrazyGoat\Forklift\Server\Socket\ForkSharedProxy;
use CrazyGoat\Forklift\Server\Socket\MasterProxy;
use CrazyGoat\Forklift\Server\Socket\ReusePortProxy;
use CrazyGoat\Forklift\Server\Socket\SocketFactory;
use CrazyGoat\Forklift\Server\Types\ProtocolType;
use CrazyGoat\Forklift\Server\Types\ProxyType;
use PHPUnit\Framework\TestCase;

class SocketFactoryTest extends TestCase
{
    public function testCreateMasterReturnsMasterProxy(): void
    {
        $proxy = SocketFactory::create(ProxyType::MASTER, 8080, ProtocolType::TCP);

        $this->assertInstanceOf(MasterProxy::class, $proxy);
    }

    public function testCreateForkSharedReturnsForkSharedProxy(): void
    {
        $proxy = SocketFactory::create(ProxyType::FORK_SHARED, 8080, ProtocolType::HTTP);

        $this->assertInstanceOf(ForkSharedProxy::class, $proxy);
    }

    public function testCreateReusePortFallbacksToForkShared(): void
    {
        $proxy = SocketFactory::create(ProxyType::REUSE_PORT, 8080, ProtocolType::HTTP);

        $this->assertTrue($proxy instanceof ReusePortProxy || $proxy instanceof ForkSharedProxy);
    }
}
