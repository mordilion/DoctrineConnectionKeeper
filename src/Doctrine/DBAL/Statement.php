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
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement as DBALStatement;
use Doctrine\DBAL\Driver\Statement as DBALDriverStatement;

/**
 * @author Henning Huncke <mordilion@gmx.de>
 */
class Statement extends DBALStatement
{
    /**
     * Statement constructor.
     *
     * @param DBALConnection      $conn
     * @param DBALDriverStatement $statement
     * @param string              $sql
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function __construct(Connection $conn, DBALDriverStatement $statement, string $sql)
    {
        parent::__construct($conn, $statement, $sql);
    }

    /**
     * @param mixed[]|null $params
     *
     * @return Result
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
