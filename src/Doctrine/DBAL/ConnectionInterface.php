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

/**
 * @author Henning Huncke <mordilion@gmx.de>
 */
interface ConnectionInterface
{
    public function handle(callable $tryCallable, ?callable $catchCallable = null): void;

    public function getCatchCallable(): ?callable;

    public function setCatchCallable(?callable $catchCallable): void;
}
