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

/**
 * @author Henning Huncke <mordilion@gmx.de>
 */
class Connection extends DBALConnection implements ConnectionInterface
{
    use ConnectionTrait;
}
