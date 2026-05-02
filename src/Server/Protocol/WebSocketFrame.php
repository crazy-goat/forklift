<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Server\Protocol;

class WebSocketFrame
{
    private const MAX_7BIT = 125;
    private const MAX_16BIT = 65535;

    public function __construct(
        public readonly int $opcode,
        public readonly string $payload,
        public readonly bool $fin = true,
    ) {
    }

    public static function encode(string $data, int $opcode = 0x1): string
    {
        $length = \strlen($data);

        $frame = \pack('C', 0x80 | $opcode);

        if ($length <= self::MAX_7BIT) {
            $frame .= \chr($length);
        } elseif ($length <= self::MAX_16BIT) {
            $frame .= \chr(126) . \pack('n', $length);
        } else {
            $frame .= \chr(127) . \pack('J', $length);
        }

        return $frame . $data;
    }

    private static function readUint16(string $data, int $offset): ?int
    {
        $unpacked = \unpack('n', substr($data, $offset, 2));

        if ($unpacked === false) {
            return null;
        }

        $value = $unpacked[1];

        return \is_int($value) ? $value : null;
    }

    private static function readUint64(string $data, int $offset): ?int
    {
        $unpacked = \unpack('J', substr($data, $offset, 8));

        if ($unpacked === false) {
            return null;
        }

        $value = $unpacked[1];

        return \is_int($value) ? $value : null;
    }

    public static function decode(string $data): ?self
    {
        $len = \strlen($data);

        if ($len < 2) {
            return null;
        }

        $firstByte = \ord($data[0]);
        $secondByte = \ord($data[1]);

        $fin = (bool) ($firstByte & 0x80);
        $opcode = $firstByte & 0x0f;
        $masked = (bool) ($secondByte & 0x80);
        $payloadLengthCode = $secondByte & 0x7f;

        $offset = 2;
        $payloadLength = $payloadLengthCode;

        if ($payloadLengthCode === 126) {
            if ($len < $offset + 2) {
                return null;
            }
            $read = self::readUint16($data, $offset);
            if ($read === null) {
                return null;
            }
            $payloadLength = $read;
            $offset += 2;
        } elseif ($payloadLengthCode === 127) {
            if ($len < $offset + 8) {
                return null;
            }
            $read = self::readUint64($data, $offset);
            if ($read === null) {
                return null;
            }
            $payloadLength = $read;
            $offset += 8;
        }

        if ($masked) {
            if ($len < $offset + 4) {
                return null;
            }
            $mask = \substr($data, $offset, 4);
            $offset += 4;
        }

        if ($len < $offset + $payloadLength) {
            return null;
        }

        $payload = \substr($data, $offset, $payloadLength);

        if ($masked) {
            $unmasked = '';
            for ($i = 0; $i < $payloadLength; $i++) {
                $unmasked .= \chr((\ord($payload[$i]) ^ \ord($mask[$i % 4])) & 0xff);
            }
            $payload = $unmasked;
        }

        return new self($opcode, $payload, $fin);
    }

    public static function frameSize(string $data): ?int
    {
        $len = \strlen($data);

        if ($len < 2) {
            return null;
        }

        $secondByte = \ord($data[1]);
        $payloadLengthCode = $secondByte & 0x7f;
        $masked = (bool) ($secondByte & 0x80);

        $headerSize = 2;
        $payloadLength = $payloadLengthCode;

        if ($payloadLengthCode === 126) {
            if ($len < $headerSize + 2) {
                return null;
            }
            $read = self::readUint16($data, 2);
            if ($read === null) {
                return null;
            }
            $payloadLength = $read;
            $headerSize += 2;
        } elseif ($payloadLengthCode === 127) {
            if ($len < $headerSize + 8) {
                return null;
            }
            $read = self::readUint64($data, 2);
            if ($read === null) {
                return null;
            }
            $payloadLength = $read;
            $headerSize += 8;
        }

        if ($masked) {
            $headerSize += 4;
        }

        if (\strlen($data) < $headerSize + $payloadLength) {
            return null;
        }

        return $headerSize + $payloadLength;
    }
}
