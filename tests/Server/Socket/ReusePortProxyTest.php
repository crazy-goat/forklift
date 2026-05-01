<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Tests\Server\Socket;

use CrazyGoat\Forklift\Server\Socket\Connection;
use CrazyGoat\Forklift\Server\Socket\ReusePortProxy;
use CrazyGoat\Forklift\Server\Socket\Socket;
use CrazyGoat\Forklift\Server\Socket\SocketProxyInterface;
use CrazyGoat\Forklift\Server\Types\ProtocolType;
use PHPUnit\Framework\TestCase;

class ReusePortProxyTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $proxy = new ReusePortProxy();

        $this->assertInstanceOf(SocketProxyInterface::class, $proxy);
    }

    public function testCreateSocketReturnsSocket(): void
    {
        $proxy = new ReusePortProxy();

        $socket = $proxy->createSocket(0, ProtocolType::TCP);

        $this->assertInstanceOf(Socket::class, $socket);

        $socket->close();
    }

    public function testAcceptReturnsConnection(): void
    {
        $proxy = new ReusePortProxy();

        $socket = $proxy->createSocket(0, ProtocolType::TCP);

        \socket_getsockname($this->getSocketResource($socket), $address, $portNumber);

        $client = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($client);

        /** @var string $address */
        /** @var int $portNumber */
        \socket_connect($client, $address, $portNumber);

        $connection = $proxy->accept($socket);

        $this->assertInstanceOf(Connection::class, $connection);

        \socket_close($client);
        $connection->close();
        $socket->close();
    }

    public function testIsSupportedFalseOnWindows(): void
    {
        $proxy = new ReusePortProxy();

        if (PHP_OS_FAMILY === 'Windows') {
            $this->assertFalse($proxy->isSupported());
        } else {
            $expected = defined('SO_REUSEPORT');
            $this->assertSame($expected, $proxy->isSupported());
        }
    }

    private function getSocketResource(Socket $socket): \Socket
    {
        $reflection = new \ReflectionProperty(Socket::class, 'resource');

        $resource = $reflection->getValue($socket);
        $this->assertInstanceOf(\Socket::class, $resource);

        return $resource;
    }
}
