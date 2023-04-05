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

    public function register(object $entity, mixed $identifier): void
    {
        $class = $this->getMetadataFactory()->getMetadataFor(ltrim(get_class($entity), '\\'));

        if (!is_array($identifier)) {
            $identifier = [$class->identifier[0] => $identifier];
        }

        $this->getUnitOfWork()->registerManaged($entity, $identifier, []);
    }

    /**
     * @param callable $func
     *
     * @return mixed
     * @throws RetryableException
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
     * @throws RetryableException
     * @throws Throwable
     */
    public function wrapInTransaction($func)
    {
        return $this->handle($func);
    }

    /**
     * @throws RetryableException
     * @throws Throwable
     */
    private function handle(callable $tryCallable)
    {
        $result = null;
        $attempt = 0;
        $tryClosure = \Closure::fromCallable($tryCallable);

        do {
            $retry = false;
            $this->beginTransaction();

            try {
                $result = $tryClosure($this);

                $this->flush();
                $this->commit();
            } catch (RetryableException|EntityManagerClosed $exception) {
                if ($this->getConnection()->isTransactionActive()) {
                    $this->rollback();
                }

                $this->close();
                $this->wrapped = $this->registry->resetManager($this->wrappedName);

                $retry = $attempt < $this->retryAttempts;
                $attempt++;

                if (!$retry) {
                    throw $exception;
                }
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
