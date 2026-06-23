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
     * @param FlushMode $flush Defines when and how the Entity Manager flushes its pending changes:
     *        - {@see FlushMode::OnWrite} flushes every operation immediately;
     *        - {@see FlushMode::BeforeCommit} flushes all collected changes once before committing;
     *        - {@see FlushMode::FailOnPending} throws if any changes are left unflushed;
     *        - {@see FlushMode::SkipPending} silently skips any unflushed changes.
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
        FlushMode $flush = FlushMode::BeforeCommit,
    ): mixed;
}
