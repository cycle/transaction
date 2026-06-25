<?php

declare(strict_types=1);

namespace Cycle\Transaction;

use Cycle\ORM\Exception\RunnerException;
use Cycle\Transaction\Exception\TransactionException;

enum TransactionMode
{
    /**
     * Do nothing with transactions.
     *
     * @see \Cycle\ORM\Transaction\Runner::outerTransaction() with non-strict mode.
     */
    case Ignore;

    /**
     * The currently opened transaction will be used. If no transaction is opened, a {@see RunnerException}
     * exception will be thrown.
     *
     * @see \Cycle\ORM\Transaction\Runner::outerTransaction() with strict mode.
     */
    case Current;

    /**
     * A new transaction will be open for each used driver connection and will close they on finish.
     *
     * @see \Cycle\ORM\Transaction\Runner::innerTransaction()
     */
    case OpenNew;

    /**
     * Like {@see self::OpenNew}, a new transaction is always opened for the Unit of Work and closed on
     * finish, but it is additionally required to be exclusive: the transaction must be the top-level one
     * and not wrapped by any outer transaction. If an outer transaction is already open, a
     * {@see TransactionException} will be thrown before any work is done.
     *
     * This guarantees that the changes made within the transaction cannot be rolled back by a surrounding
     * transaction once committed. It is useful, for example, for an idempotency service that must be sure
     * its work is durably committed and not silently discarded by an enclosing transaction.
     *
     * @see \Cycle\ORM\Transaction\Runner::innerTransaction()
     */
    case Exclusive;
}
