<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Tests\Server\Protocol;

use CrazyGoat\Forklift\Server\Protocol\WebSocketFrame;
use PHPUnit\Framework\TestCase;

class WebSocketFrameTest extends TestCase
{
    public function testEncodeTextFrame(): void
    {
        $frame = WebSocketFrame::encode('Hello');

        $this->assertSame(0x81, \ord($frame[0]));
        $this->assertSame(5, \ord($frame[1]));
        $this->assertSame('Hello', substr($frame, 2));
    }

    public function testEncodeBinaryFrame(): void
    {
        $hex = \hex2bin('deadbeef');
        $this->assertNotFalse($hex);
        $frame = WebSocketFrame::encode($hex, 0x02);

        $this->assertSame(0x82, \ord($frame[0]));
        $this->assertSame(4, \ord($frame[1]));
        $this->assertSame($hex, substr($frame, 2));
    }

    public function testEncodeDecodeRoundTrip(): void
    {
        $original = 'Hello, WebSocket!';
        $encoded = WebSocketFrame::encode($original);
        $decoded = WebSocketFrame::decode($encoded);

        $this->assertInstanceOf(WebSocketFrame::class, $decoded);
        $this->assertSame(0x01, $decoded->opcode);
        $this->assertSame($original, $decoded->payload);
        $this->assertTrue($decoded->fin);
    }

    public function testDecodeReturnsNullForEmptyData(): void
    {
        $this->assertNull(WebSocketFrame::decode(''));
    }

    public function testDecodeReturnsNullForSingleByte(): void
    {
        $this->assertNull(WebSocketFrame::decode("\x81"));
    }

    public function testDecodeReturnsNullForIncompleteSmallPayload(): void
    {
        $frame = "\x81\x0aHello";
        $this->assertNull(WebSocketFrame::decode($frame));
    }

    public function testDecodeReturnsNullForIncompleteExtendedPayload(): void
    {
        $frame = "\x81\x7e\x00\x0a" . 'Hello';
        $this->assertNull(WebSocketFrame::decode($frame));
    }

    public function testDecodeReturnsFrameForCompleteSmallPayload(): void
    {
        $frame = "\x81\x05Hello";
        $decoded = WebSocketFrame::decode($frame);

        $this->assertInstanceOf(WebSocketFrame::class, $decoded);
        $this->assertSame('Hello', $decoded->payload);
        $this->assertSame(0x01, $decoded->opcode);
        $this->assertTrue($decoded->fin);
    }

    public function testFrameSizeReturnsNullForEmptyData(): void
    {
        $this->assertNull(WebSocketFrame::frameSize(''));
    }

    public function testFrameSizeReturnsNullForSingleByte(): void
    {
        $this->assertNull(WebSocketFrame::frameSize("\x81"));
    }

    public function testFrameSizeReturnsCorrectSizeForSmallPayload(): void
    {
        $frame = "\x81\x05Hello";
        $this->assertSame(7, WebSocketFrame::frameSize($frame));
    }

    public function testFrameSizeReturnsNullForIncompleteFrame(): void
    {
        $frame = "\x81\x7e\x10\x00" . \str_repeat('a', 100);
        $this->assertNull(WebSocketFrame::frameSize($frame));
    }

    public function testFrameSizeWithMaskedFrame(): void
    {
        $mask = \pack('N', 0x12345678);
        $payload = 'Hello';
        $maskedPayload = '';
        for ($i = 0; $i < \strlen($payload); $i++) {
            $maskedPayload .= \chr((\ord($payload[$i]) ^ \ord($mask[$i % 4])) & 0xff);
        }
        $frame = "\x81\x85" . $mask . $maskedPayload;

        $this->assertSame(11, WebSocketFrame::frameSize($frame));
    }

    public function testDecodeUnmasksPayload(): void
    {
        $mask = "\x12\x34\x56\x78";
        $payload = 'Hello';
        $masked = '';
        for ($i = 0; $i < \strlen($payload); $i++) {
            $masked .= \chr((\ord($payload[$i]) ^ \ord($mask[$i % 4])) & 0xff);
        }
        $frame = "\x81\x85" . $mask . $masked;

        $decoded = WebSocketFrame::decode($frame);

        $this->assertInstanceOf(WebSocketFrame::class, $decoded);
        $this->assertSame('Hello', $decoded->payload);
    }

    public function test16BitExtendedPayloadLength(): void
    {
        $payload = \str_repeat('A', 200);
        $encoded = WebSocketFrame::encode($payload);

        $this->assertSame(0x81, \ord($encoded[0]));
        $this->assertSame(126, \ord($encoded[1]));
        $unpacked = \unpack('n', substr($encoded, 2, 2));
        $this->assertNotFalse($unpacked);
        $this->assertSame(200, $unpacked[1]);

        $decoded = WebSocketFrame::decode($encoded);
        $this->assertInstanceOf(WebSocketFrame::class, $decoded);
        $this->assertSame($payload, $decoded->payload);
    }

    public function test64BitExtendedPayloadLength(): void
    {
        $payload = \str_repeat('B', 70000);
        $encoded = WebSocketFrame::encode($payload);

        $this->assertSame(0x81, \ord($encoded[0]));
        $this->assertSame(127, \ord($encoded[1]));
        $unpacked = \unpack('J', substr($encoded, 2, 8));
        $this->assertNotFalse($unpacked);
        $this->assertSame(70000, $unpacked[1]);

        $decoded = WebSocketFrame::decode($encoded);
        $this->assertInstanceOf(WebSocketFrame::class, $decoded);
        $this->assertSame($payload, $decoded->payload);
    }

    public function testFrameSize16BitExtended(): void
    {
        $payload = \str_repeat('C', 300);
        $encoded = WebSocketFrame::encode($payload);
        $expectedSize = 2 + 2 + \strlen($payload);

        $this->assertSame($expectedSize, WebSocketFrame::frameSize($encoded));
    }

    public function testFrameSize64BitExtended(): void
    {
        $payload = \str_repeat('D', 70000);
        $encoded = WebSocketFrame::encode($payload);
        $expectedSize = 2 + 8 + \strlen($payload);

        $this->assertSame($expectedSize, WebSocketFrame::frameSize($encoded));
    }

    public function testDecodeFrameWithFinFalse(): void
    {
        $frame = "\x01\x05Hello";
        $decoded = WebSocketFrame::decode($frame);

        $this->assertInstanceOf(WebSocketFrame::class, $decoded);
        $this->assertFalse($decoded->fin);
    }

    public function testDecodeReturnsNullForIncomplete16BitLengthField(): void
    {
        $frame = "\x81\x7e\x00";
        $this->assertNull(WebSocketFrame::decode($frame));
    }

    public function testDecodeReturnsNullForIncomplete64BitLengthField(): void
    {
        $frame = "\x81\xff\x00\x00\x00\x00\x00\x00\x00";
        $this->assertNull(WebSocketFrame::decode($frame));
    }

    public function testEncodeAlwaysSetsFin(): void
    {
        $encoded = WebSocketFrame::encode('Hello', 0x01);

        $this->assertSame(0x81, \ord($encoded[0]));
    }
}
