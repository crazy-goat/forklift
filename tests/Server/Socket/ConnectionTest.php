<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Tests\Server\Socket;

use CrazyGoat\Forklift\Server\Socket\Connection;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    /** @return array{Connection, \Socket} */
    private function createConnectedPair(): array
    {
        $srv = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($srv);
        \socket_set_option($srv, SOL_SOCKET, SO_REUSEADDR, 1);
        \socket_bind($srv, '127.0.0.1', 0);
        \socket_listen($srv);

        \socket_getsockname($srv, $addr, $port);

        $client = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($client);
        /** @var string $addr */
        /** @var int $port */
        $this->assertNotFalse(\socket_connect($client, $addr, $port));

        $accepted = \socket_accept($srv);
        $this->assertNotFalse($accepted);

        \socket_close($srv);

        $connection = new Connection($accepted);

        return [$connection, $client];
    }

    public function testReadReturnsString(): void
    {
        /** @var Connection $connection */
        /** @var \Socket $client */
        [$connection, $client] = $this->createConnectedPair();

        \socket_write($client, 'hello', 5);

        $data = $connection->read(5);

        $this->assertSame('hello', $data);

        \socket_close($client);
        $connection->close();
    }

    public function testReadReturnsFalseWhenClosed(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $connection = new Connection($resource);
        $connection->close();

        $this->assertFalse($connection->read());
    }

    public function testWriteReturnsInt(): void
    {
        /** @var Connection $connection */
        /** @var \Socket $client */
        [$connection, $client] = $this->createConnectedPair();

        $result = $connection->write('world');

        $this->assertSame(5, $result);

        \socket_close($client);
        $connection->close();
    }

    public function testWriteReturnsFalseWhenClosed(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $connection = new Connection($resource);
        $connection->close();

        $this->assertFalse($connection->write('data'));
    }

    public function testCloseIsIdempotent(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $connection = new Connection($resource);

        $connection->close();
        $connection->close();

        $this->assertTrue($connection->isClosed());
    }

    public function testIsClosedInitiallyFalse(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $connection = new Connection($resource);

        $this->assertFalse($connection->isClosed());

        $connection->close();
    }

    public function testIsClosedReturnsTrueAfterClose(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $connection = new Connection($resource);
        $connection->close();

        $this->assertTrue($connection->isClosed());
    }

    public function testGetPeerNameReturnsHostAndPort(): void
    {
        /** @var Connection $connection */
        /** @var \Socket $client */
        [$connection, $client] = $this->createConnectedPair();

        $peer = $connection->getPeerName();

        $this->assertArrayHasKey('host', $peer);
        $this->assertArrayHasKey('port', $peer);
        $this->assertSame('127.0.0.1', $peer['host']);

        \socket_close($client);
        $connection->close();
    }

    public function testGetPeerNameThrowsWhenClosed(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $connection = new Connection($resource);
        $connection->close();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection is closed');

        $connection->getPeerName();
    }

    public function testGetPeerNameThrowsOnFailedSocketCall(): void
    {
        $resource = \socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $this->assertNotFalse($resource);

        $connection = new Connection($resource);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to get peer name');

        $connection->getPeerName();

        \socket_close($resource);
    }

    public function testGetLastActivityUpdatedOnRead(): void
    {
        /** @var Connection $connection */
        /** @var \Socket $client */
        [$connection, $client] = $this->createConnectedPair();

        $before = $connection->getLastActivity();
        \usleep(10_000);

        \socket_write($client, 'x', 1);
        $connection->read(1);

        $after = $connection->getLastActivity();

        $this->assertGreaterThan($before, $after);

        \socket_close($client);
        $connection->close();
    }

    public function testGetLastActivityUpdatedOnWrite(): void
    {
        /** @var Connection $connection */
        /** @var \Socket $client */
        [$connection, $client] = $this->createConnectedPair();

        $before = $connection->getLastActivity();
        \usleep(10_000);

        $connection->write('x');

        $after = $connection->getLastActivity();

        $this->assertGreaterThan($before, $after);

        \socket_close($client);
        $connection->close();
    }

    public function testGetLastActivityNotUpdatedOnFailedRead(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $connection = new Connection($resource);
        $connection->close();

        $before = $connection->getLastActivity();
        \usleep(1);

        $connection->read();

        $this->assertSame($before, $connection->getLastActivity());
    }

    public function testGetLastActivityNotUpdatedOnFailedWrite(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $connection = new Connection($resource);
        $connection->close();

        $before = $connection->getLastActivity();
        \usleep(1);

        $connection->write('x');

        $this->assertSame($before, $connection->getLastActivity());
    }

    public function testSetOptionSilentlyReturnsWhenClosed(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $connection = new Connection($resource);
        $connection->close();

        $exception = null;
        try {
            $connection->setOption(SOL_SOCKET, SO_REUSEADDR, 1);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $this->assertNull($exception, 'setOption should not throw when connection is closed');
        $this->assertTrue($connection->isClosed());
    }

    public function testSetOptionThrowsOnFailedSocketCall(): void
    {
        $resource = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($resource);

        $connection = new Connection($resource);

        $this->expectException(\RuntimeException::class);

        $connection->setOption(SOL_SOCKET, SO_RCVBUF, -1);

        \socket_close($resource);
    }

    public function testDefaultReadLength(): void
    {
        /** @var Connection $connection */
        /** @var \Socket $client */
        [$connection, $client] = $this->createConnectedPair();

        \socket_write($client, str_repeat('x', 70000), 70000);

        $data = $connection->read();

        $this->assertIsString($data);
        $this->assertGreaterThan(0, strlen($data));

        \socket_close($client);
        $connection->close();
    }
}
