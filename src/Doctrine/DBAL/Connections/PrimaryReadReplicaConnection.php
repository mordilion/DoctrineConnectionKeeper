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

namespace Mordilion\DoctrineConnectionKeeper\Doctrine\DBAL\Connections;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection as DBALPrimaryReadReplicaConnectionAlias;
use Doctrine\DBAL;
use Mordilion\DoctrineConnectionKeeper\Doctrine\DBAL\ConnectionInterface;
use Mordilion\DoctrineConnectionKeeper\Doctrine\DBAL\ConnectionTrait;
use Mordilion\DoctrineConnectionKeeper\Doctrine\DBAL\Statement;

/**
 * @author Henning Huncke <mordilion@gmx.de>
 */
class PrimaryReadReplicaConnection extends DBALPrimaryReadReplicaConnectionAlias implements ConnectionInterface
{
    use ConnectionTrait {
        ConnectionTrait::prepare as traitPrepare;
    }

    /**
     * PrimaryReadReplicaConnection constructor.
     *
     * @param array                   $params
     * @param DBAL\Driver             $driver
     * @param DBAL\Configuration|null $config
     * @param EventManager|null       $eventManager
     *
     * @throws DBAL\Exception
     */
    public function __construct(array $params, DBAL\Driver $driver, ?DBAL\Configuration $config = null, ?EventManager $eventManager = null)
    {
        $this->handleParams($params['connection_keeper'] ?? []);

        parent::__construct($params, $driver, $config, $eventManager);
    }

    /**
     * @param string $sql
     *
     * @return Statement
     * @throws DBAL\Exception
     */
    public function prepare(string $sql): Statement
    {
        $this->ensureConnectedToPrimary();

        return $this->traitPrepare($sql);
    }
}
