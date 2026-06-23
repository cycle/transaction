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
}
