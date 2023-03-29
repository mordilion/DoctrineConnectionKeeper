<?php

declare(strict_types=1);

namespace Mordilion\DoctrineConnectionKeeper\Doctrine\DBAL\Functional;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Mordilion\DoctrineConnectionKeeper\Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ConnectionTest extends TestCase
{
    public function testConnection()
    {
        $connection = new Connection(
            [
                'connection_keeper' => [
                    'reconnect_attempts' => 3,
                    'refresh_on_exception' => true,
                    'handle_retryable_exceptions' => true,
                ],
            ],
            $this->createMock(Driver::class),
            $this->createMock(Configuration::class),
            $this->createMock(EventManager::class)
        );

        $reflection = new ReflectionClass($connection);

        $this->assertSame(3, $reflection->getProperty('reconnectAttempts')->getValue($connection));
        $this->assertSame(true, $reflection->getProperty('refreshOnException')->getValue($connection));
        $this->assertSame(true, $reflection->getProperty('handleRetryableExceptions')->getValue($connection));
    }
}
