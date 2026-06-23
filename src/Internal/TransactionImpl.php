<?php

declare(strict_types=1);

namespace Cycle\Transaction\Internal;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Transaction\Runner;
use Cycle\Transaction\Exception\TransactionException;
use Cycle\Transaction\FlushMode;
use Cycle\Transaction\Transaction;
use Cycle\Transaction\TransactionMode;

final class TransactionImpl implements Transaction
{
    public function __construct(
        private readonly ORMInterface $orm,
        private readonly DatabaseProviderInterface $dbs,
    ) {}

    #[\Override]
    public function transact(
        callable $callback,
        ?string $source = null,
        TransactionMode $emMode = TransactionMode::Current,
        FlushMode $flush = FlushMode::BeforeCommit,
    ): mixed {
        // Resolve DB name from the entity role
        $db = $this->resolveDatabase($source);

        // Create Entity Manager instance
        $em = new EntityManager(
            $this->orm,
            $this->getRunner($emMode),
            $db->getDriver()->getName(),
            $flush,
        );

        $db->begin();
        try {
            $result = $callback($em, $db);

            // Resolve the pending changes according to the flush mode
            match ($flush) {
                // OnWrite has already flushed each operation; the final run() is a no-op.
                FlushMode::OnWrite,
                FlushMode::BeforeCommit => $em->run(),
                FlushMode::FailOnPending => $em->hasPendingChanges()
                    ? throw new TransactionException('Entity Manager has pending changes.')
                    : null,
                FlushMode::SkipPending => null,
            };

            // Commit transaction and return the callback result
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Create a transaction runner based on the provided mode.
     */
    private static function getRunner(TransactionMode $mode): Runner
    {
        return match ($mode) {
            TransactionMode::Ignore => Runner::outerTransaction(strict: false),
            TransactionMode::Current => Runner::outerTransaction(strict: true),
            TransactionMode::OpenNew => Runner::innerTransaction(),
        };
    }

    /**
     * Resolve the database instance based on the provided source.
     *
     * @param non-empty-string|class-string|null $source
     */
    private function resolveDatabase(?string $source): DatabaseInterface
    {
        return match(true) {
            $source === null  => $this->dbs->database(),
           \class_exists($source) => $this->orm->getSource($this->orm->resolveRole($source))->getDatabase(),
            default => $this->dbs->database($source),
        };
    }
}
