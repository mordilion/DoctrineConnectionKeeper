# DoctrineConnectionKeeper
Keep the connection to your MySQL-Server alive!

If an error happens like "MySQL server has gone away", then it will retry the failed statement. If the error happens again after the maximum retry attempts, then it throws it.

##Config Example
```php
return [
    'doctrine' => [
        'connection' => [
            'orm_default' => [
                'wrapperClass' => \Mordilion\DoctrineConnectionKeeper\Doctrine\DBAL\Connection::class,
                'params' => [
                    'driver' => 'pdo_mysql',
                    'host' => 'localhost',
                    'port' => '3307',
                    'user' => '***',
                    'password' => '***',
                    'dbname' => '***',
                    'charset' => 'UTF8',
                    'connection_keeper' => [
                        'reconnect_attempts' => 3,
                        'refresh_on_exception' => true,             
                    ],
                ],
            ],
        ],
    ],
];
```
