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

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection as DBALPrimaryReadReplicaConnectionAlias;
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

    public function prepare(string $sql): Statement
    {
        $this->ensureConnectedToPrimary();

        return $this->traitPrepare($sql);
    }
}
