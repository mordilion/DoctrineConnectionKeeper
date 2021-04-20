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

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Throwable;

/**
 * @author Henning Huncke <mordilion@gmx.de>
 */
trait ConnectionTrait
{
    /**
     * @var int
     */
    private $reconnectAttempts = 0;

    /**
     * @var bool
     */
    private $refreshOnException = false;

    /**
     * @return bool
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
     * @return \Doctrine\DBAL\ForwardCompatibility\DriverResultStatement|\Doctrine\DBAL\ForwardCompatibility\DriverStatement|\Doctrine\DBAL\ForwardCompatibility\Result
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
     * @return \Doctrine\DBAL\Driver\Statement
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

    /**
     * @return bool
     */
    public function refresh(): bool
    {
        try {
            $unique = '"' . uniqid('ping_', true) . '"';
            $this->executeQuery('SELECT ' . $unique);
        } catch (Throwable $exception) {
            return false;
        }

        return true;
    }

    /**
     * @param array $params
     */
    protected function handleParams(array $params): void
    {
        $this->setReconnectAttempts((int) ($params['reconnect_attempts'] ?? 0));
        $this->setRefreshOnException((bool) ($params['refresh_on_exception'] ?? false));
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
     * @param callable      $tryCallable
     * @param callable|null $catchCallable
     */
    private function handle(callable $tryCallable, ?callable $catchCallable = null): void
    {
        $attempt = 0;
        $catchCallable = $catchCallable ?? function () { $this->close(); };

        do {
            $retry = false;

            try {
                $tryCallable();
            } catch (Throwable $exception) {
                $catchCallable();

                if ($this->refreshOnException) {
                    $this->refresh();
                }

                if (!$this->isGoneAwayException($exception)) {
                    throw $exception;
                }

                $retry = $attempt < $this->reconnectAttempts;
                $attempt++;
            }
        } while ($retry);
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
