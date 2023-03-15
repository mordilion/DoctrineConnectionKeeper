<?php

declare(strict_types=1);

namespace Mordilion\DoctrineConnectionKeeper\Doctrine\DBAL\Functional;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Mordilion\DoctrineConnectionKeeper\Doctrine\DBAL\Connection;

final class ConnectionTest extends \PHPUnit\Framework\TestCase
{
    public function testConnection()
    {
        $connection = new Connection(
            [
                'connection_keeper' => [
                    'reconnect_attempts' => 3,
                    'refresh_on_exception' => true,
                ],
            ],
            $this->createMock(Driver::class),
            $this->createMock(Configuration::class),
            $this->createMock(EventManager::class)
        );

        $this->assertSame(3, $connection->getReconnectAttempts());
    }
}
