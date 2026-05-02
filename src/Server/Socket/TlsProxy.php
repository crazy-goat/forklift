<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Server\Socket;

use CrazyGoat\Forklift\Server\Exception\SocketCreationException;
use CrazyGoat\Forklift\Server\Types\ProtocolType;

class TlsProxy implements SocketProxyInterface
{
    /**
     * @param array<string, mixed> $sslContextOptions
     */
    public function __construct(
        private readonly SocketProxyInterface $inner,
        private readonly string $certFile,
        private readonly string $keyFile,
        private readonly array $sslContextOptions = [],
    ) {
    }

    public function createSocket(int $port, ProtocolType $protocol): Socket
    {
        return $this->inner->createSocket($port, $protocol);
    }

    /**
     * @throws SocketCreationException
     */
    public function accept(Socket $socket): Connection
    {
        $connection = $this->inner->accept($socket);

        $reflection = new \ReflectionProperty(Connection::class, 'resource');
        /** @var \Socket $socketResource */
        $socketResource = $reflection->getValue($connection);

        $stream = @\socket_export_stream($socketResource);

        if ($stream === false) {
            $error = \error_get_last();

            throw new SocketCreationException(
                \is_array($error) ? $error['message'] : 'Failed to export socket to stream',
            );
        }

        \stream_set_timeout($stream, 30);

        if (!\stream_context_set_option($stream, 'ssl', 'local_cert', $this->certFile)) {
            \fclose($stream);

            throw new SocketCreationException('Failed to set SSL context option: local_cert');
        }

        if (!\stream_context_set_option($stream, 'ssl', 'local_pk', $this->keyFile)) {
            \fclose($stream);

            throw new SocketCreationException('Failed to set SSL context option: local_pk');
        }

        foreach ($this->sslContextOptions as $option => $value) {
            if (!\stream_context_set_option($stream, 'ssl', $option, $value)) {
                \fclose($stream);

                throw new SocketCreationException(
                    \sprintf('Failed to set SSL context option: %s', $option),
                );
            }
        }

        $result = @\stream_socket_enable_crypto($stream, true, \STREAM_CRYPTO_METHOD_TLS_SERVER);

        if ($result === false) {
            $error = \error_get_last();
            \fclose($stream);

            throw new SocketCreationException(
                \sprintf(
                    'TLS handshake failed: %s',
                    \is_array($error) ? $error['message'] : 'unknown error',
                ),
            );
        }

        return $connection;
    }

    public function isSupported(): bool
    {
        return \function_exists('stream_socket_enable_crypto')
            && \function_exists('socket_export_stream');
    }
}
