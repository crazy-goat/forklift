<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Tests\Server\Socket;

use CrazyGoat\Forklift\Server\Exception\SocketCreationException;
use CrazyGoat\Forklift\Server\Socket\Connection;
use CrazyGoat\Forklift\Server\Socket\Socket;
use CrazyGoat\Forklift\Server\Socket\SocketProxyInterface;
use CrazyGoat\Forklift\Server\Socket\TlsProxy;
use CrazyGoat\Forklift\Server\Types\ProtocolType;
use PHPUnit\Framework\TestCase;

class TlsProxyTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $inner = $this->createMock(SocketProxyInterface::class);
        $proxy = new TlsProxy($inner, '/tmp/cert.pem', '/tmp/key.pem');

        $this->assertInstanceOf(SocketProxyInterface::class, $proxy);
    }

    public function testCreateSocketDelegatesToInner(): void
    {
        $expectedSocket = $this->createMock(Socket::class);

        $inner = $this->createMock(SocketProxyInterface::class);
        $inner->expects($this->once())
            ->method('createSocket')
            ->with(8080, ProtocolType::TCP)
            ->willReturn($expectedSocket);

        $proxy = new TlsProxy($inner, '/tmp/cert.pem', '/tmp/key.pem');

        $result = $proxy->createSocket(8080, ProtocolType::TCP);

        $this->assertSame($expectedSocket, $result);
    }

    public function testAcceptThrowsOnTlsHandshakeFailure(): void
    {
        $server = @\socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($server);

        \socket_bind($server, '127.0.0.1', 0);
        \socket_listen($server);

        \socket_getsockname($server, $address, $port);

        /** @var string $address */
        /** @var int $port */
        $client = @\socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertNotFalse($client);
        \socket_connect($client, $address, $port);

        $clientSocket = @\socket_accept($server);
        $this->assertNotFalse($clientSocket);

        $connection = new Connection($clientSocket);

        $inner = $this->createMock(SocketProxyInterface::class);
        $inner->expects($this->once())
            ->method('accept')
            ->willReturn($connection);

        $mockSocket = $this->createMock(Socket::class);

        $proxy = new TlsProxy($inner, '/tmp/nonexistent-cert.pem', '/tmp/nonexistent-key.pem');

        $this->expectException(SocketCreationException::class);
        $this->expectExceptionMessage('TLS handshake failed');

        $proxy->accept($mockSocket);

        \socket_close($client);
        \socket_close($server);
    }

    public function testIsSupported(): void
    {
        $inner = $this->createMock(SocketProxyInterface::class);
        $proxy = new TlsProxy($inner, '/tmp/cert.pem', '/tmp/key.pem');

        $expected = \function_exists('stream_socket_enable_crypto')
            && \function_exists('socket_export_stream');

        $this->assertSame($expected, $proxy->isSupported());
    }
}
