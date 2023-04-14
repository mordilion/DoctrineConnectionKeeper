<?php

/**
 * This file is part of the DoctrineConnectionKeeper package.
 * For the full copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 * @copyright (c) Henning Huncke - <mordilion@gmx.de>
 */

declare(strict_types=1);

namespace Mordilion\DoctrineConnectionKeeper\Doctrine\ORM;

use Closure;
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

    private int $retryAttempts = 1;

    private int $retrySleepMicroseconds = 50;

    private ?string $wrappedName;

    public function __construct(EntityManagerInterface $wrapped, ManagerRegistry $registry, ?string $wrappedName = null)
    {
        parent::__construct($wrapped);

        $this->registry = $registry;
        $this->repositoryFactory = $wrapped->getConfiguration()->getRepositoryFactory();
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

    public function getRetryAttempts(): int
    {
        return $this->retryAttempts;
    }

    public function getRetrySleepMicroseconds(): int
    {
        return $this->retrySleepMicroseconds;
    }

    public function setRetryAttempts(int $retryAttempts): void
    {
        $this->retryAttempts = $retryAttempts;
    }

    public function setRetrySleepMicroseconds(int $retrySleepMicroseconds): void
    {
        $this->retrySleepMicroseconds = $retrySleepMicroseconds;
    }

    public function getWrappedName(): ?string
    {
        return $this->wrappedName;
    }

    /**
     * @param callable $func
     *
     * @return mixed
     * @throws Throwable
     */
    public function transactional($func)
    {
        return $this->handle($func);
    }

    /**
     * @param callable $func
     *
     * @return mixed
     * @throws Throwable
     */
    public function wrapInTransaction($func)
    {
        return $this->handle($func);
    }

    /**
     * @throws Throwable
     */
    private function handle(callable $tryCallable)
    {
        $result = null;
        $attempt = 0;
        $tryClosure = Closure::fromCallable($tryCallable);

        do {
            $retry = false;
            $this->beginTransaction();

            try {
                $result = $tryClosure($this);

                if ($this->getConnection()->isTransactionActive()) {
                    $this->flush();
                    $this->commit();
                }
            } catch (RetryableException|EntityManagerClosed $exception) {
                if ($this->getConnection()->isTransactionActive()) {
                    $this->rollback();
                }

                if (!$this->isOpen()) {
                    $this->close();
                }

                $this->wrapped = $this->registry->resetManager($this->wrappedName);

                $retry = $attempt < $this->retryAttempts;
                $attempt++;

                if (!$retry) {
                    throw $exception;
                }

                usleep($this->retrySleepMicroseconds);
            } catch (Throwable $exception) {
                if ($this->getConnection()->isTransactionActive()) {
                    $this->rollback();
                }

                throw $exception;
            }
        } while ($retry);

        return $result;
    }
}
