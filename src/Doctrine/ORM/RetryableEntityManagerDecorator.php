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

namespace Mordilion\DoctrineConnectionKeeper\Doctrine\ORM;

use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\EntityManagerClosed;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Throwable;

/**
 * @author Henning Huncke <mordilion@gmx.de>
 */
class RetryableEntityManagerDecorator extends EntityManagerDecorator
{
    private ManagerRegistry $registry;

    private RepositoryFactory $repositoryFactory;

    private int $retryAttempts;

    private ?string $wrappedName;

    public function __construct(EntityManagerInterface $wrapped, ManagerRegistry $registry, int $retryAttempts = 1, ?string $wrappedName = null)
    {
        parent::__construct($wrapped);

        $this->registry = $registry;
        $this->repositoryFactory = $wrapped->getConfiguration()->getRepositoryFactory();
        $this->retryAttempts = $retryAttempts;
        $this->wrappedName = $wrappedName;
    }

    /**
     * @param string $className
     *
     * @return EntityRepository|ObjectRepository
     */
    public function getRepository($className)
    {
        return $this->repositoryFactory->getRepository($this, $className);
    }

    /**
     * @param callable $func
     *
     * @return mixed
     * @throws EntityManagerClosed
     * @throws RetryableException
     */
    public function transactional($func)
    {
        $result = null;

        $this->handle(function () use (&$result, $func) {
            $result = $this->internalWrapInTransaction($func);
        });

        return $result;
    }

    /**
     * @param callable $func
     *
     * @return mixed
     * @throws EntityManagerClosed
     * @throws RetryableException
     */
    public function wrapInTransaction($func)
    {
        $result = null;

        $this->handle(function () use (&$result, $func) {
            $result = $this->internalWrapInTransaction($func);
        });

        return $result;
    }

    /**
     * @throws RetryableException
     * @throws EntityManagerClosed
     */
    private function handle(callable $tryCallable)
    {
        $attempt = 0;
        $tryClosure = \Closure::fromCallable($tryCallable);

        do {
            $retry = false;

            try {
                $tryClosure();
            } catch (RetryableException|EntityManagerClosed $exception) {
                $this->wrapped = $this->registry->resetManager($this->wrappedName);

                $retry = $attempt < $this->retryAttempts;
                $attempt++;

                if (!$retry) {
                    throw $exception;
                }
            }
        } while ($retry);
    }

    /**
     * @throws Throwable
     */
    private function internalWrapInTransaction(callable $func)
    {
        $this->beginTransaction();

        try {
            $result = $func($this);

            $this->flush();
            $this->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->close();

            if ($this->getConnection()->isTransactionActive()) {
                $this->rollBack();
            }

            throw $exception;
        }
    }
}
