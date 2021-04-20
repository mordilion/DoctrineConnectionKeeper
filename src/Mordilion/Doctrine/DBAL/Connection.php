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

namespace Mordilion\Doctrine\DBAL;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver;

/**
 * @author Henning Huncke <mordilion@gmx.de>
 */
class Connection extends DBALConnection
{
    use ConnectionTrait;

    /**
     * Connection constructor.
     *
     * @param array              $params
     * @param Driver             $driver
     * @param Configuration|null $config
     * @param EventManager|null  $eventManager
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function __construct(array $params, Driver $driver, ?Configuration $config = null, ?EventManager $eventManager = null)
    {
        $this->handleParams($params['connection_keeper'] ?? []);

        parent::__construct($params, $driver, $config, $eventManager);
    }
}
