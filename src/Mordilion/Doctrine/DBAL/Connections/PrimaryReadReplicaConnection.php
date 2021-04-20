<?php

/**
 * This file is part of the DoctrineConnectionKeeper package.
 *
 * For the full copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 *
 * @copyright (c) Henning Huncke - <mordilion@gmx.de>
 */

declare(strict_types=1);

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection as DBALPrimaryReadReplicaConnectionAlias;
use Doctrine\DBAL;
use Mordilion\Doctrine\DBAL\ConnectionTrait;

/**
 * @author Henning Huncke <mordilion@gmx.de>
 */
class PrimaryReadReplicaConnection extends DBALPrimaryReadReplicaConnectionAlias
{
    use ConnectionTrait;

    /**
     * PrimaryReadReplicaConnection constructor.
     *
     * @param array                   $params
     * @param DBAL\Driver             $driver
     * @param DBAL\Configuration|null $config
     * @param EventManager|null       $eventManager
     */
    public function __construct(array $params, DBAL\Driver $driver, ?DBAL\Configuration $config = null, ?EventManager $eventManager = null)
    {
        $this->handleParams($params['connection_keeper'] ?? []);

        parent::__construct($params, $driver, $config, $eventManager);
    }
}
