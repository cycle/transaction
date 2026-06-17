<?php

declare(strict_types=1);

namespace Cycle\Transaction;

use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManagerInterface;
use Cycle\Transaction\Exception\TransactionException;

interface Transaction
{
    /**
     * Open a new DB transaction and execute the callback within it.
     *
     * All the DBAL operations within the callback will be executed within a single transaction.
     * If an exception is thrown within the callback, the transaction will be rolled back,
     * otherwise it will be committed.
     *
     * @template TResult
     * @param callable(EntityManagerInterface, DatabaseInterface): TResult $callback
     * @param non-empty-string|class-string|null $source The database connection name or entity class.
     *        If null, the default database connection will be used.
     * @param TransactionMode $emMode The transaction mode for the Entity Manager.
     * @param bool $autoRun Call {@see EntityManagerInterface::run()} before committing the transaction.
     *        If false, the Entity Manager will be checked to ensure there are no pending changes, and an exception
     *        will be thrown if there are any.
     *
     * @return TResult
     *
     * It creates a new Unit of Work ({@see EntityManagerInterface}) for the duration of the transaction.
     * The Entity Manager will fail if used to manage entities outside the given $source.
     *
     * @throws TransactionException
     * @throws \Throwable
     */
    public function transact(
        callable $callback,
        ?string $source = null,
        TransactionMode $emMode = TransactionMode::Current,
        bool $autoRun = true,
    ): mixed;
}
