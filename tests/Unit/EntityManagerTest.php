<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Unit;

use Cycle\ORM\Transaction\Runner;
use Cycle\ORM\Transaction\StateInterface;
use Cycle\Transaction\Exception\TransactionException;
use Cycle\Transaction\FlushMode;
use Cycle\Transaction\Internal\EmptyState;
use Cycle\Transaction\Internal\EntityManager;
use Cycle\Transaction\Tests\Fixtures\Post;
use Cycle\Transaction\Tests\Fixtures\SqliteEnvironment;
use Cycle\Transaction\Tests\Fixtures\TestEnvironment;
use Cycle\Transaction\Tests\Fixtures\User;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(EntityManager::class)]
final class EntityManagerTest
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

    public function runWithoutQueuedEntitiesReturnsEmptyState(): void
    {
        $em = $this->makeManager();

        $state = $em->run();

        Assert::instanceOf($state, EmptyState::class);
    }

    public function hasNoPendingChangesInitially(): void
    {
        $em = $this->makeManager();

        Assert::false($em->hasPendingChanges());
    }

    public function persistQueuesPendingChange(): void
    {
        $em = $this->makeManager();

        $em->persist(new User('a@example.com', 10));

        Assert::true($em->hasPendingChanges());
    }

    public function persistStateQueuesPendingChange(): void
    {
        $em = $this->makeManager();

        $em->persistState(new User('b@example.com', 20));

        Assert::true($em->hasPendingChanges());
    }

    public function persistReturnsSameManagerForChaining(): void
    {
        $em = $this->makeManager();

        Assert::same($em->persist(new User('c@example.com')), $em);
    }

    public function cleanDiscardsPendingChanges(): void
    {
        $em = $this->makeManager();
        $em->persist(new User('d@example.com'));

        $em->clean();

        Assert::false($em->hasPendingChanges());
    }

    public function runPersistsEntityWithinAnOpenedTransaction(): void
    {
        $db = $this->env->dbal->database('default');
        $db->begin();

        $em = $this->makeManager(); // Runner::Current — requires an open transaction
        $user = new User('persist@example.com', 99);
        $em->persist($user);
        $state = $em->run();

        $db->commit();

        Assert::true($state->isSuccess());
        Assert::same($db->table('user')->count(), 1);
        Assert::false($em->hasPendingChanges());
    }

    public function runReturnsStateInterface(): void
    {
        $db = $this->env->dbal->database('default');
        $db->begin();

        $em = $this->makeManager();
        $em->persist(new User('state@example.com'));
        $state = $em->run();

        $db->commit();

        Assert::instanceOf($state, StateInterface::class);
    }

    public function rejectsEntityFromAnotherDriver(): never
    {
        $em = $this->makeManager(); // bound to the `default` (user) driver

        Expect::exception(TransactionException::class)
            ->withMessageContaining('does not match the transaction database driver');

        // Post is mapped to the `secondary` connection -> different driver name.
        $em->persist(new Post('mismatch'));
    }

    public function deleteRejectsEntityFromAnotherDriver(): never
    {
        $em = $this->makeManager();

        Expect::exception(TransactionException::class);

        $em->delete(new Post('mismatch'));
    }

    public function persistStateRejectsEntityFromAnotherDriver(): never
    {
        $em = $this->makeManager();

        Expect::exception(TransactionException::class)
            ->withMessageContaining('does not match the transaction database driver');

        $em->persistState(new Post('mismatch'));
    }

    public function persistStateWithOnWriteFlushFlushesWithoutExplicitRun(): void
    {
        $db = $this->env->dbal->database('default');
        $db->begin();
        $em = new EntityManager(
            $this->env->orm,
            Runner::outerTransaction(strict: true),
            $db->getDriver()->getName(),
            flush: FlushMode::OnWrite,
        );

        $em->persistState(new User('autostate@example.com'));
        $db->commit();

        Assert::same($db->table('user')->count(), 1);
    }

    public function persistWithOnWriteFlushFlushesWithoutExplicitRun(): void
    {
        $db = $this->env->dbal->database('default');
        $db->begin();
        $em = new EntityManager(
            $this->env->orm,
            Runner::outerTransaction(strict: true),
            $db->getDriver()->getName(),
            flush: FlushMode::OnWrite,
        );

        $em->persist(new User('autopersist@example.com'));
        $db->commit();

        Assert::same($db->table('user')->count(), 1);
    }

    public function onWriteFlushLeavesNoPendingChangesAfterEachWrite(): void
    {
        $db = $this->env->dbal->database('default');
        $db->begin();
        $em = new EntityManager(
            $this->env->orm,
            Runner::outerTransaction(strict: true),
            $db->getDriver()->getName(),
            flush: FlushMode::OnWrite,
        );

        $em->persist(new User('onwrite@example.com'));

        // OnWrite flushed immediately, so the UoW must already be clean.
        Assert::same($em->hasPendingChanges(), false);
        $db->commit();
    }

    public function beforeCommitFlushDefersUntilRun(): void
    {
        $db = $this->env->dbal->database('default');
        $db->begin();
        $em = $this->makeManager(); // defaults to FlushMode::BeforeCommit

        $em->persist(new User('deferred@example.com'));

        // BeforeCommit must NOT flush on write: the change stays pending until run().
        Assert::same($em->hasPendingChanges(), true);
        Assert::same($db->table('user')->count(), 0);

        $em->run();
        $db->commit();

        Assert::same($db->table('user')->count(), 1);
    }

    public function runResetsUoWForSubsequentCalls(): void
    {
        $db = $this->env->dbal->database('default');
        $db->begin();
        $em = $this->makeManager();
        $em->persist(new User('reset@example.com'));
        $em->run();
        $db->commit();

        // After run(), uow must be null — second run() must return EmptyState immediately
        Assert::instanceOf($em->run(), EmptyState::class);
    }

    public function persistMultipleEntitiesAllAreSaved(): void
    {
        $db = $this->env->dbal->database('default');
        $db->begin();
        $em = $this->makeManager();

        $em->persist(new User('first@example.com'));
        $em->persist(new User('second@example.com'));
        $em->run();
        $db->commit();

        Assert::same($db->table('user')->count(), 2);
    }

    public function deleteRemovesPersistedEntity(): void
    {
        $db = $this->env->dbal->database('default');
        $db->begin();
        $em = $this->makeManager();

        $user = new User('todelete@example.com');
        $em->persist($user);
        $em->run();

        $em->delete($user);
        $em->run();
        $db->commit();

        Assert::same($db->table('user')->count(), 0);
    }

    public function deleteWithOnWriteFlushRemovesWithoutExplicitRun(): void
    {
        $db = $this->env->dbal->database('default');
        $db->begin();
        $em = new EntityManager(
            $this->env->orm,
            Runner::outerTransaction(strict: true),
            $db->getDriver()->getName(),
            flush: FlushMode::OnWrite,
        );

        $user = new User('delonwrite@example.com');
        $em->persist($user); // flushed immediately
        $em->delete($user);  // flushed immediately
        $db->commit();

        Assert::same($db->table('user')->count(), 0);
    }

    public function runThrowsWhenUnitOfWorkFails(): never
    {
        $db = $this->env->dbal->database('default');
        $db->begin();
        $em = $this->makeManager();

        // Two fresh entities sharing the same primary key violate the PK constraint on flush.
        $first = new User('dup@example.com');
        $first->id = 1;
        $second = new User('dup-too@example.com');
        $second->id = 1;
        $em->persist($first);
        $em->persist($second);

        try {
            Expect::exception(\Throwable::class);
            $em->run();
        } finally {
            $db->rollback();
        }
    }

    public function runThrowsActualDatabaseErrorWhenStateHasError(): void
    {
        $db = $this->env->dbal->database('default');
        $db->begin();
        $em = $this->makeManager();

        // Create a duplicate that will cause a constraint violation.
        // The UnitOfWork will attempt to run and capture the error.
        // Original code: throw $state->getLastError() ?? new RuntimeException(...)
        // Mutated code: throw new RuntimeException(...) ?? $state->getLastError()
        // The mutation ignores the actual error and throws the generic RuntimeException.
        $first = new User('dup@example.com');
        $first->id = 1;
        $second = new User('dup-too@example.com');
        $second->id = 1;
        $em->persist($first);
        $em->persist($second);

        try {
            // We can't use Expect::exception on em->run() because the test framework
            // won't let us catch the exception to check the message. Instead, we verify
            // that a RuntimeException with the generic message is NOT thrown.
            $exceptionThrown = null;
            try {
                $em->run();
            } catch (\Throwable $e) {
                $exceptionThrown = $e;
            }

            Assert::notNull($exceptionThrown, 'Expected an exception to be thrown');

            // The critical check: with the mutation, this WOULD be a RuntimeException
            // with message 'Transaction failed with unknown error'
            if ($exceptionThrown instanceof \RuntimeException &&
                $exceptionThrown->getMessage() === 'Transaction failed with unknown error') {
                Assert::true(false, 'Got generic RuntimeException - mutation applied!');
            }
        } finally {
            $db->rollback();
        }
    }


    private function makeManager(): EntityManager
    {
        return new EntityManager(
            $this->env->orm,
            Runner::outerTransaction(strict: true),
            $this->env->dbal->database('default')->getDriver()->getName(),
        );
    }
}
