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
use Doctrine\DBAL\ForwardCompatibility;
use Throwable;

/**
 * @author Henning Huncke <mordilion@gmx.de>
 */
trait ConnectionTrait
{
    private int $reconnectAttempts = 0;

    private bool $refreshOnException = false;

    /**
     * @return bool
     * @throws Throwable
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
     * @param string                 $sql
     * @param array                  $params
     * @param array                  $types
     * @param QueryCacheProfile|null $qcp
     *
     * @return ForwardCompatibility\DriverResultStatement|ForwardCompatibility\DriverStatement|ForwardCompatibility\Result
     * @throws Throwable
     */
    public function executeQuery($sql, array $params = [], $types = [], ?QueryCacheProfile $qcp = null)
    {
        $result = null;

        $this->handle(function () use (&$result, $sql, $params, $types, $qcp) {
            $result = parent::executeQuery($sql, $params, $types, $qcp);
        });

        return $result;
    }

    /**
     * @param string $sql
     * @param array  $params
     * @param array  $types
     *
     * @return int
     * @throws Throwable
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
     * @param string $sql
     * @param array  $params
     * @param array  $types
     *
     * @return int
     * @throws Throwable
     */
    public function executeUpdate($sql, array $params = [], array $types = [])
    {
        $result = 0;

        $this->handle(function () use (&$result, $sql, $params, $types) {
            $result = parent::executeUpdate($sql, $params, $types);
        });

        return $result;
    }

    /**
     * @param callable      $tryCallable
     * @param callable|null $catchCallable
     *
     * @throws Throwable
     */
    public function handle(callable $tryCallable, ?callable $catchCallable = null): void
    {
        $attempt = 0;

        do {
            $retry = false;

            try {
                $tryCallable();
            } catch (Throwable $exception) {
                $this->close();
                $this->connect();

                if ($catchCallable !== null) {
                    $catchCallable($exception);
                }

                if (!$this->isGoneAwayException($exception)) {
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
     * @param string $sql
     *
     * @return Statement
     * @throws \Doctrine\DBAL\Exception
     */
    public function prepare($sql)
    {
        try {
            $stmt = new Statement($sql, $this);
        } catch (Throwable $exception) {
            $this->handleExceptionDuringQuery($exception, $sql);
        }

        $stmt->setFetchMode($this->defaultFetchMode);

        return $stmt;
    }

    /**
     * @return \Doctrine\DBAL\Driver\Statement
     * @throws Throwable
     */
    public function query()
    {
        $result = null;
        $args = func_get_args();

        $this->handle(function () use (&$result, $args) {
            $result = parent::query(...$args);
        });

        return $result;
    }

    public function refresh(): void
    {
        $unique = '"' . uniqid('ping_', true) . '"';
        $this->executeQuery('SELECT ' . $unique);
    }

    /**
     * @param array $params
     */
    protected function handleParams(array $params): void
    {
        $this->setReconnectAttempts((int) ($params['reconnect_attempts'] ?? 0));
        $this->setRefreshOnException((bool) filter_var($params['refresh_on_exception'] ?? false, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * @param int $reconnectAttempts
     */
    protected function setReconnectAttempts(int $reconnectAttempts): void
    {
        $this->reconnectAttempts = $reconnectAttempts;
    }

    /**
     * @param bool $refreshOnException
     */
    protected function setRefreshOnException(bool $refreshOnException): void
    {
        $this->refreshOnException = $refreshOnException;
    }

    /**
     * @param Throwable $exception
     *
     * @return bool
     */
    private function isGoneAwayException(Throwable $exception): bool
    {
        $exceptionMessage = $exception->getMessage();
        $messages = [
            'MySQL server has gone away',
            'Lost connection to MySQL server during query',
        ];

        foreach ($messages as $message) {
            if (stripos($exceptionMessage, $message) !== false) {
                return true;
            }
        }

        return false;
    }
}
