<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Tests\Server\Socket;

use CrazyGoat\Forklift\Server\Socket\Connection;
use CrazyGoat\Forklift\Server\Socket\MasterProxy;
use CrazyGoat\Forklift\Server\Socket\Socket;
use CrazyGoat\Forklift\Server\Socket\SocketProxyInterface;
use CrazyGoat\Forklift\Server\Types\ProtocolType;
use PHPUnit\Framework\TestCase;

class MasterProxyTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $proxy = new MasterProxy();

        $this->assertInstanceOf(SocketProxyInterface::class, $proxy);
    }

    public function testCreateSocketReturnsSocket(): void
    {
        $proxy = new MasterProxy();

        $socket = $proxy->createSocket(0, ProtocolType::TCP);

        $this->assertInstanceOf(Socket::class, $socket);

        $socket->close();
    }

    public function testAcceptReturnsConnection(): void
    {
        $proxy = new MasterProxy();

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

    public function testIsSupported(): void
    {
        $proxy = new MasterProxy();

        $expected = \function_exists('msg_get_queue') && \function_exists('socket_sendmsg');
        $this->assertSame($expected, $proxy->isSupported());
    }

    private function getSocketResource(Socket $socket): \Socket
    {
        $reflection = new \ReflectionProperty(Socket::class, 'resource');

        $resource = $reflection->getValue($socket);
        $this->assertInstanceOf(\Socket::class, $resource);

        return $resource;
    }
}
