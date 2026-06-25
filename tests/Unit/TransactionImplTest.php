<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Unit;

use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManagerInterface;
use Cycle\Transaction\Exception\TransactionException;
use Cycle\Transaction\FlushMode;
use Cycle\Transaction\Internal\TransactionImpl;
use Cycle\Transaction\Tests\Fixtures\SqliteEnvironment;
use Cycle\Transaction\Tests\Fixtures\TestEnvironment;
use Cycle\Transaction\Tests\Fixtures\User;
use Cycle\Transaction\TransactionMode;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(TransactionImpl::class)]
final class TransactionImplTest
{
    private TestEnvironment $env;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->env = SqliteEnvironment::create();
    }

    #[AfterTest]
    public function tearDown(): void
    {
        $this->env->dropTables();
    }

    public function returnsCallbackResult(): void
    {
        $result = $this->env->transaction->transact(
            static fn(): string => 'the-result',
        );

        Assert::same($result, 'the-result');
    }

    public function passesEntityManagerAndDatabaseToCallback(): void
    {
        $this->env->transaction->transact(function ($em, $db): void {
            Assert::instanceOf($em, EntityManagerInterface::class);
            Assert::instanceOf($db, DatabaseInterface::class);
        });
    }

    public function commitsPersistedEntityOnSuccess(): void
    {
        $this->env->transaction->transact(static function (EntityManagerInterface $em): void {
            $em->persist(new User('commit@example.com', 5));
        });

        Assert::same($this->env->dbal->database('default')->table('user')->count(), 1);
    }

    public function rollsBackOnException(): never
    {
        Expect::exception(\DomainException::class)->withMessage('boom');

        try {
            $this->env->transaction->transact(static function (EntityManagerInterface $em): void {
                $em->persist(new User('rollback@example.com', 5));
                throw new \DomainException('boom');
            });
        } finally {
            // The persisted row must not survive the rolled-back transaction.
            Assert::same($this->env->dbal->database('default')->table('user')->count(), 0);
        }
    }

    public function failOnPendingWithPendingChangesThrows(): never
    {
        Expect::exception(TransactionException::class)
            ->withMessage('Entity Manager has pending changes.');

        $this->env->transaction->transact(
            callback: static function (EntityManagerInterface $em): void {
                $em->persist(new User('pending@example.com'));
            },
            flush: FlushMode::FailOnPending,
        );
    }

    public function failOnPendingRollsBackWhenGuardTrips(): void
    {
        try {
            $this->env->transaction->transact(
                callback: static function (EntityManagerInterface $em): void {
                    $em->persist(new User('pending@example.com'));
                },
                flush: FlushMode::FailOnPending,
            );
        } catch (TransactionException) {
            // expected
        }

        Assert::same($this->env->dbal->database('default')->table('user')->count(), 0);
    }

    public function failOnPendingWithoutPendingChangesCommits(): void
    {
        $result = $this->env->transaction->transact(
            callback: static fn(): int => 7,
            flush: FlushMode::FailOnPending,
        );

        Assert::same($result, 7);
    }

    public function failOnPendingCommitsWhenCallbackFlushesManually(): void
    {
        $this->env->transaction->transact(
            callback: static function (EntityManagerInterface $em): void {
                $em->persist(new User('manual@example.com'));
                $em->run(); // no pending changes remain -> guard passes
            },
            flush: FlushMode::FailOnPending,
        );

        Assert::same($this->env->dbal->database('default')->table('user')->count(), 1);
    }

    public function onWriteFlushPersistsWithoutManualRun(): void
    {
        $this->env->transaction->transact(
            callback: static function (EntityManagerInterface $em): void {
                $em->persist(new User('onwrite@example.com'));
                // No explicit run(): OnWrite flushes each operation immediately.
            },
            flush: FlushMode::OnWrite,
        );

        Assert::same($this->env->dbal->database('default')->table('user')->count(), 1);
    }

    public function skipPendingCommitsButDiscardsUnflushedChanges(): void
    {
        $this->env->transaction->transact(
            callback: static function (EntityManagerInterface $em): void {
                $em->persist(new User('skipped@example.com'));
                // Left unflushed on purpose: SkipPending must commit silently and drop it.
            },
            flush: FlushMode::SkipPending,
        );

        // The DBAL transaction committed, but the unflushed entity was never written.
        Assert::same($this->env->dbal->database('default')->table('user')->count(), 0);
    }

    public function skipPendingStillCommitsManuallyFlushedChanges(): void
    {
        $this->env->transaction->transact(
            callback: static function (EntityManagerInterface $em): void {
                $em->persist(new User('flushed@example.com'));
                $em->run(); // explicitly flushed -> survives the commit
            },
            flush: FlushMode::SkipPending,
        );

        Assert::same($this->env->dbal->database('default')->table('user')->count(), 1);
    }

    public function resolvesDatabaseByConnectionName(): void
    {
        $this->env->transaction->transact(
            callback: static function (EntityManagerInterface $em, DatabaseInterface $db): void {
                Assert::same($db->getName(), 'secondary');
            },
            source: 'secondary',
        );
    }

    public function resolvesDatabaseByEntityClass(): void
    {
        // User is mapped to the `default` database.
        $this->env->transaction->transact(
            callback: static function (EntityManagerInterface $em, DatabaseInterface $db): void {
                Assert::same($db->getName(), 'default');
            },
            source: User::class,
        );
    }

    public function resolvesDefaultDatabaseWhenSourceIsNull(): void
    {
        $this->env->transaction->transact(
            callback: static function (EntityManagerInterface $em, DatabaseInterface $db): void {
                Assert::same($db->getName(), 'default');
            },
            source: null,
        );
    }

    public function commitTransactionAfterSuccessfulCallback(): void
    {
        $db = $this->env->dbal->database('default');

        $this->env->transaction->transact(static fn(): null => null);

        // After a successful transact(), the DBAL transaction must be fully committed (level = 0)
        Assert::same($db->getDriver()->getTransactionLevel(), 0);
    }

    public function rollsBackChangesAlreadyFlushedInsideCallback(): void
    {
        try {
            $this->env->transaction->transact(static function (EntityManagerInterface $em): void {
                $em->persist(new User('flushed@example.com'));
                $em->run(); // flush ORM changes into the open DBAL transaction
                throw new \DomainException('after flush');
            });
        } catch (\DomainException) {
            // expected
        }

        // Rollback must have undone the flushed entity
        Assert::same($this->env->dbal->database('default')->table('user')->count(), 0);
    }

    public function openNewModeCommitsWithoutAnOuterTransaction(): void
    {
        // OpenNew opens its own inner transaction for the UoW, independent of the outer begin().
        $this->env->transaction->transact(
            callback: static function (EntityManagerInterface $em): void {
                $em->persist(new User('opennew@example.com'));
            },
            emMode: TransactionMode::OpenNew,
        );

        Assert::same($this->env->dbal->database('default')->table('user')->count(), 1);
    }

    public function exclusiveModeCommitsWhenNoOuterTransactionIsOpen(): void
    {
        $this->env->transaction->transact(
            callback: static function (EntityManagerInterface $em): void {
                $em->persist(new User('exclusive@example.com'));
            },
            emMode: TransactionMode::Exclusive,
        );

        Assert::same($this->env->dbal->database('default')->table('user')->count(), 1);
    }

    public function exclusiveModeOpensItsOwnInnerTransaction(): void
    {
        // Exclusive uses an inner transaction for the UoW, independent of the outer begin().
        // We close the outer transaction inside the callback; the EM must still persist via its own
        // transaction. If Exclusive reused the current (outer, strict) transaction instead, calling
        // run() after the outer transaction is gone would throw a RunnerException.
        $this->env->transaction->transact(
            callback: static function (EntityManagerInterface $em, DatabaseInterface $db): void {
                $db->rollback(); // close the outer transaction opened by transact()
                $em->persist(new User('exclusive-inner@example.com'));
                $em->run();      // opens and commits its own inner transaction
            },
            emMode: TransactionMode::Exclusive,
        );

        Assert::same($this->env->dbal->database('default')->table('user')->count(), 1);
    }

    public function exclusiveModeThrowsWhenWrappedByAnotherTransaction(): never
    {
        Expect::exception(TransactionException::class)
            ->withMessage('An exclusive transaction cannot be started while another transaction is already open.');

        $db = $this->env->dbal->database('default');

        // Open an outer transaction so the exclusive one is wrapped.
        $db->begin();
        try {
            $this->env->transaction->transact(
                callback: static function (EntityManagerInterface $em): void {
                    $em->persist(new User('wrapped@example.com'));
                },
                emMode: TransactionMode::Exclusive,
            );
        } finally {
            $db->rollback();
        }
    }

    public function ignoreModeCommitsUsingOuterTransaction(): void
    {
        $this->env->transaction->transact(
            callback: static function (EntityManagerInterface $em): void {
                $em->persist(new User('ignore@example.com'));
            },
            emMode: TransactionMode::Ignore,
        );

        Assert::same($this->env->dbal->database('default')->table('user')->count(), 1);
    }

    public function ignoreModeDoesNotCheckTransactionStatusWhenNoTransactionIsOpen(): void
    {
        $this->env->transaction->transact(
            callback: static function (EntityManagerInterface $em, DatabaseInterface $db): void {
                $em->persist(new User('first@example.com'));
                $em->run(); // Flush first entity

                // Rollback the outer transaction to close it
                $db->rollback();

                // Try to persist another entity after rollback.
                // With strict: false, this should not throw because Ignore mode doesn't check transaction state.
                // With strict: true (the mutation), this SHOULD throw RunnerException.
                $em->persist(new User('second@example.com'));
                $em->run();

                // If we reach here, Ignore mode correctly ignored the transaction state
                Assert::true(true);
            },
            emMode: TransactionMode::Ignore,
        );
    }

    public function currentModeThrowsUnlikeIgnoreMode(): void
    {
        // Ignore mode successfully operates even after the transaction is rolled back
        $ignoreModeCompleted = false;
        try {
            $this->env->transaction->transact(
                callback: static function (EntityManagerInterface $em, DatabaseInterface $db): void {
                    $em->persist(new User('ignore1@example.com'));
                    $em->run();
                    $db->rollback();
                    $em->persist(new User('ignore2@example.com'));
                    $em->run();
                },
                emMode: TransactionMode::Ignore,
            );
            $ignoreModeCompleted = true;
        } catch (\Throwable $e) {
            // Unexpected in Ignore mode
            Assert::fail('Ignore mode should not throw when transaction is closed: ' . $e->getMessage());
        }

        // Current mode should throw when trying to operate after transaction is rolled back
        // (because strict: true requires an open transaction)
        $currentModeThrew = false;
        try {
            $this->env->transaction->transact(
                callback: static function (EntityManagerInterface $em, DatabaseInterface $db): void {
                    $em->persist(new User('current1@example.com'));
                    $em->run();
                    $db->rollback();
                    $em->persist(new User('current2@example.com'));
                    $em->run();
                },
                emMode: TransactionMode::Current,
            );
        } catch (\Throwable) {
            // Expected in Current mode (strict: true)
            $currentModeThrew = true;
        }

        // With original code: Ignore succeeds (strict:false), Current throws (strict:true)
        // With mutation: Both would succeed (both strict:false)
        Assert::true($ignoreModeCompleted, 'Ignore mode should complete without throwing');
        Assert::true($currentModeThrew, 'Current mode should throw unlike Ignore mode');
    }
}
