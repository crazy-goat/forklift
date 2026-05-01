<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Tests\Server\Socket;

use CrazyGoat\Forklift\Server\Exception\SocketAcceptException;
use CrazyGoat\Forklift\Server\Exception\SocketCreationException;
use CrazyGoat\Forklift\Server\Socket\Connection;
use CrazyGoat\Forklift\Server\Socket\Socket;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class SocketTest extends TestCase
{
    public function testConstructorWrapsResource(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $socket = new Socket($resource);

        $this->assertInstanceOf(Socket::class, $socket);

        \socket_close($resource);
    }

    public function testBindAndListen(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $socket = new Socket($resource);
        $socket->setOption(SOL_SOCKET, SO_REUSEADDR, 1);
        $socket->bind('127.0.0.1', 0);
        $socket->listen(5);

        $socket->close();
    }

    public function testAcceptReturnsConnection(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        \socket_set_option($resource, SOL_SOCKET, SO_REUSEADDR, 1);
        \socket_bind($resource, '127.0.0.1', 0);
        \socket_getsockname($resource, $addr, $port);
        $this->assertIsString($addr);
        $this->assertIsInt($port);

        \socket_listen($resource);
        $socket = new Socket($resource);

        $client = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($client);

        \socket_connect($client, $addr, $port);

        $connection = $socket->accept();

        $this->assertInstanceOf(Connection::class, $connection);

        \socket_close($client);
        $connection->close();
        $socket->close();
    }

    public function testCloseIsIdempotent(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $socket = new Socket($resource);

        $socket->close();
        $socket->close();
    }

    public function testCloseSetsResourceToNull(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $socket = new Socket($resource);
        $socket->close();

        $reflection = new ReflectionProperty(Socket::class, 'resource');

        $this->assertNull($reflection->getValue($socket));
    }

    public function testAcceptThrowsAfterClose(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $socket = new Socket($resource);
        $socket->close();

        $this->expectException(SocketAcceptException::class);

        $socket->accept();
    }

    public function testBindThrowsAfterClose(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $socket = new Socket($resource);
        $socket->close();

        $this->expectException(SocketCreationException::class);

        $socket->bind('127.0.0.1', 8080);
    }

    public function testListenThrowsAfterClose(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $socket = new Socket($resource);
        $socket->close();

        $this->expectException(SocketCreationException::class);

        $socket->listen();
    }

    public function testSetOptionThrowsAfterClose(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $socket = new Socket($resource);
        $socket->close();

        $this->expectException(SocketCreationException::class);

        $socket->setOption(SOL_SOCKET, SO_REUSEADDR, 1);
    }
}
