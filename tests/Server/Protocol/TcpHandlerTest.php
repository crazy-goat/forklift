<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Tests\Server\Protocol;

use CrazyGoat\Forklift\Server\Protocol\TcpHandler;
use CrazyGoat\Forklift\Server\Socket\Connection;
use PHPUnit\Framework\TestCase;

class TcpHandlerTest extends TestCase
{
    public function testNoCallbackIsNoOp(): void
    {
        $socket = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($socket);

        $connection = new Connection($socket);
        $handler = new TcpHandler();

        $handler->handle($connection);

        $this->assertFalse($connection->isClosed());

        $connection->close();
    }

    public function testCallbackIsCalledWithConnection(): void
    {
        $socket = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($socket);

        $connection = new Connection($socket);

        $called = false;
        $passedConnection = null;

        $handler = new TcpHandler(function (Connection $conn) use (&$called, &$passedConnection): void {
            $called = true;
            $passedConnection = $conn;
        });

        $handler->handle($connection);

        $this->assertTrue($called);
        $this->assertSame($connection, $passedConnection);

        $connection->close();
    }

    public function testHandlerDoesNotCloseConnection(): void
    {
        $socket = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($socket);

        $connection = new Connection($socket);

        $handler = new TcpHandler(function (Connection $conn): void {
        });

        $handler->handle($connection);

        $this->assertFalse($connection->isClosed());

        $connection->close();
    }
}
