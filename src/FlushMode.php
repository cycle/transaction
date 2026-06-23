<?php

declare(strict_types=1);

namespace Cycle\Transaction;

/**
 * Defines how the scoped {@see \Cycle\ORM\EntityManagerInterface} flushes its
 * pending changes within a transaction.
 */
enum FlushMode
{
    /**
     * Flush every change to the database immediately: {@see EntityManagerInterface::run()}
     * is called on each persist/persistState/delete operation.
     */
    case OnWrite;

    /**
     * Collect all changes and flush them once, right before the transaction is committed.
     */
    case BeforeCommit;

    /**
     * Do not flush automatically. If the Entity Manager still has pending changes when
     * the transaction is about to commit, a {@see Exception\TransactionException} is thrown.
     */
    case FailOnPending;

    /**
     * Do not flush automatically and silently skip any pending changes left in the
     * Entity Manager (only the DBAL operations are committed).
     */
    case SkipPending;
}
