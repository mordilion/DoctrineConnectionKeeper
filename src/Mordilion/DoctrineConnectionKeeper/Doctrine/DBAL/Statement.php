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

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Statement as DBALStatement;

/**
 * @author Henning Huncke <mordilion@gmx.de>
 */
class Statement extends DBALStatement
{
    /**
     * Statement constructor.
     *
     * @param string         $sql
     * @param DBALConnection $conn
     */
    public function __construct($sql, DBALConnection $conn)
    {
        parent::__construct($sql, $conn);
    }

    /**
     * @param null $params
     *
     * @return bool
     */
    public function execute($params = null)
    {
        /** @var ConnectionInterface $connection */
        $connection = $this->conn;
        $result = false;

        $connection->handle(function () use (&$result, $params) {
            $result = parent::execute($params);
        });

        return $result;
    }
}
