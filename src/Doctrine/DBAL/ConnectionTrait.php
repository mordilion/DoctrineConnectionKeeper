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

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Result;
use Throwable;

/**
 * @author Henning Huncke <mordilion@gmx.de>
 */
trait ConnectionTrait
{
    private bool $handleRetryableExceptions = false;

    private int $reconnectAttempts = 0;

    private bool $refreshOnException = false;

    /**
     * {@inheritDoc}
     */
    public function beginTransaction()
    {
        if (0 !== $this->getTransactionNestingLevel()) {
            return parent::beginTransaction();
        }

        $result = false;

        $this->handle(function () use (&$result) {
            $result = parent::beginTransaction();
        });

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function executeQuery(string $sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        $this->handle(function () use (&$result, $sql, $params, $types, $qcp) {
            $result = parent::executeQuery($sql, $params, $types, $qcp);
        });

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function executeStatement($sql, array $params = [], array $types = [])
    {
        $result = 0;

        $this->handle(function () use (&$result, $sql, $params, $types) {
            $result = parent::executeStatement($sql, $params, $types);
        });

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function executeUpdate(string $sql, array $params = [], array $types = []): int
    {
        $result = 0;

        $this->handle(function () use (&$result, $sql, $params, $types) {
            $result = parent::executeUpdate($sql, $params, $types);
        });

        return $result;
    }

    /**
     * @throws Throwable
     * @throws \Doctrine\DBAL\Exception
     */
    public function handle(callable $tryCallable, ?callable $catchCallable = null): void
    {
        $attempt = 0;
        $tryClosure = \Closure::fromCallable($tryCallable);
        $catchClosure = $catchCallable ? \Closure::fromCallable($catchCallable) : null;

        do {
            $retry = false;

            try {
                $tryClosure();
            } catch (Throwable $exception) {
                $this->close();
                $this->connect();

                if ($catchClosure !== null) {
                    $catchClosure($exception);
                }

                if (!$this->isGoneAwayException($exception)
                    && !($this->handleRetryableExceptions && $this->isRetryableException($exception))
                ) {
                    throw $exception;
                }

                if ($this->refreshOnException) {
                    $this->refresh();
                }

                $retry = $attempt < $this->reconnectAttempts;
                $attempt++;
            }
        } while ($retry);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function prepare(string $sql): Statement
    {
        $this->connect();
        assert($this->_conn !== null);

        try {
            $statement = $this->_conn->prepare($sql);
        } catch (DBALDriverException $exception) {
            throw $this->convertExceptionDuringQuery($exception, $sql);
        }

        return new Statement($this, $statement, $sql);
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql): Result
    {
        $this->handle(function () use (&$result, $sql) {
            $result = parent::query($sql);
        });

        return $result;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function refresh(): void
    {
        $unique = '"' . uniqid('ping_', true) . '"';
        $this->executeQuery('SELECT ' . $unique);
    }

    protected function handleParams(array $params): void
    {
        $this->setHandleRetryableExceptions((bool) filter_var($params['handle_retryable_exceptions'] ?? false, FILTER_VALIDATE_BOOLEAN));
        $this->setReconnectAttempts((int) ($params['reconnect_attempts'] ?? 0));
        $this->setRefreshOnException((bool) filter_var($params['refresh_on_exception'] ?? false, FILTER_VALIDATE_BOOLEAN));
    }

    public function getReconnectAttempts(): int
    {
        return $this->reconnectAttempts;
    }

    protected function setHandleRetryableExceptions(bool $handleRetryableExceptions): void
    {
        $this->handleRetryableExceptions = $handleRetryableExceptions;
    }

    protected function setReconnectAttempts(int $reconnectAttempts): void
    {
        $this->reconnectAttempts = $reconnectAttempts;
    }

    protected function setRefreshOnException(bool $refreshOnException): void
    {
        $this->refreshOnException = $refreshOnException;
    }

    private function isGoneAwayException(Throwable $exception): bool
    {
        $exceptionMessage = $exception->getMessage();
        $messages = [
            'MySQL server has gone away',
            'Lost connection to MySQL server during query',
            'Error while sending QUERY packet',
        ];

        foreach ($messages as $message) {
            if (stripos($exceptionMessage, $message) !== false) {
                return true;
            }
        }

        return false;
    }

    private function isRetryableException(Throwable $exception): bool
    {
        return $exception instanceof RetryableException;
    }
}
