<?php

declare(strict_types=1);

namespace CrazyGoat\Forklift\Tests\Server\Exception;

use CrazyGoat\Forklift\Server\Exception\InvalidConfigurationException;
use CrazyGoat\Forklift\Server\Exception\SocketAcceptException;
use CrazyGoat\Forklift\Server\Exception\SocketCreationException;
use CrazyGoat\Forklift\Server\Exception\WorkerFailedException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExceptionTest extends TestCase
{
    public function testSocketCreationExceptionExtendsRuntimeException(): void
    {
        $exception = new SocketCreationException();
        $this->assertInstanceOf(SocketCreationException::class, $exception);
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function testSocketAcceptExceptionExtendsRuntimeException(): void
    {
        $exception = new SocketAcceptException();
        $this->assertInstanceOf(SocketAcceptException::class, $exception);
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function testWorkerFailedExceptionExtendsRuntimeException(): void
    {
        $exception = new WorkerFailedException();
        $this->assertInstanceOf(WorkerFailedException::class, $exception);
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function testInvalidConfigurationExceptionExtendsRuntimeException(): void
    {
        $exception = new InvalidConfigurationException();
        $this->assertInstanceOf(InvalidConfigurationException::class, $exception);
        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function testExceptionAcceptanceCriteria(): void
    {
        $classes = [
            SocketCreationException::class,
            SocketAcceptException::class,
            WorkerFailedException::class,
            InvalidConfigurationException::class,
        ];

        $this->assertCount(4, $classes);

        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            $this->assertTrue($reflection->isSubclassOf(RuntimeException::class));
        }
    }
}
