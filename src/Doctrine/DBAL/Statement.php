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

namespace Mordilion\DoctrineConnectionKeeper\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement as DBALStatement;
use Doctrine\DBAL\Driver\Statement as DBALDriverStatement;

/**
 * @author Henning Huncke <mordilion@gmx.de>
 */
class Statement extends DBALStatement
{
    /**
     * {@inheritDoc}
     */
    public function __construct(Connection $conn, DBALDriverStatement $statement, string $sql)
    {
        parent::__construct($conn, $statement, $sql);
    }

    /**
     * {@inheritDoc}
     */
    public function execute($params = null): Result
    {
        /** @var ConnectionInterface $connection */
        $connection = $this->conn;

        $connection->handle(function () use (&$result, $params) {
            $result = parent::execute($params);
        });

        return $result;
    }
}
