<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Acceptance\Common;

use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Cycle\Transaction\Exception\TransactionException;
use Cycle\Transaction\Tests\Fixtures\Post;
use Cycle\Transaction\Tests\Fixtures\User;
use Cycle\Transaction\TransactionMode;
use Testo\Assert;
use Testo\Filter\Group;

/**
 * Acceptance scenarios for {@see \Cycle\Transaction\Transaction} executed against a real database.
 *
 * Abstract: discovered and run only through the concrete per-driver subclasses. Each test calls
 * {@see BaseTestCase::ensureDriver()} first, which boots the driver environment or skips the test
 * when the driver is disabled/unavailable.
 */
#[Group('driver')]
abstract class TransactionTestCase extends BaseTestCase
{
    public function commitsInsertedRow(): void
    {
        $this->ensureDriver();

        $this->transaction()->transact(static function (EntityManagerInterface $em): void {
            $em->persist(new User('alice@example.com', 100));
        });

        Assert::same($this->database()->table('user')->count(), 1);

        $row = $this->database()->table('user')->select()->run()->fetch();
        Assert::same($row['email'], 'alice@example.com');
        Assert::same((int) $row['balance'], 100);
    }

    public function commitsMultipleRowsInOneTransaction(): void
    {
        $this->ensureDriver();

        $this->transaction()->transact(static function (EntityManagerInterface $em): void {
            $em->persist(new User('a@example.com', 1));
            $em->persist(new User('b@example.com', 2));
            $em->persist(new User('c@example.com', 3));
        });

        Assert::same($this->database()->table('user')->count(), 3);
    }

    public function rollsBackOnExceptionInCallback(): void
    {
        $this->ensureDriver();

        try {
            $this->transaction()->transact(static function (EntityManagerInterface $em): void {
                $em->persist(new User('ghost@example.com', 1));
                throw new \DomainException('abort');
            });
            Assert::fail('Expected the callback exception to propagate.');
        } catch (\DomainException $e) {
            Assert::same($e->getMessage(), 'abort');
        }

        Assert::same($this->database()->table('user')->count(), 0);
    }

    public function returnsCallbackResult(): void
    {
        $this->ensureDriver();

        $result = $this->transaction()->transact(
            static fn(EntityManagerInterface $em): string => 'done',
        );

        Assert::same($result, 'done');
    }

    public function providesDatabaseAndEntityManager(): void
    {
        $this->ensureDriver();

        $this->transaction()->transact(function (EntityManagerInterface $em, DatabaseInterface $db): void {
            Assert::instanceOf($em, EntityManagerInterface::class);
            Assert::same($db->getName(), 'default');
            // The transaction must be open inside the callback.
            Assert::true($db->getDriver()->getTransactionLevel() > 0);
        });
    }

    public function dataSurvivesSequentialTransactions(): void
    {
        $this->ensureDriver();

        $this->transaction()->transact(static function (EntityManagerInterface $em): void {
            $em->persist(new User('first@example.com', 10));
        });

        $this->transaction()->transact(static function (EntityManagerInterface $em): void {
            $em->persist(new User('second@example.com', 20));
        });

        Assert::same($this->database()->table('user')->count(), 2);
    }

    public function autoRunFalseRejectsPendingChanges(): void
    {
        $this->ensureDriver();

        try {
            $this->transaction()->transact(
                callback: static function (EntityManagerInterface $em): void {
                    $em->persist(new User('pending@example.com', 1));
                },
                autoRun: false,
            );
            Assert::fail('Expected TransactionException for pending changes.');
        } catch (TransactionException $e) {
            Assert::same($e->getMessage(), 'Entity Manager has pending changes.');
        }

        // The aborted transaction must have rolled back.
        Assert::same($this->database()->table('user')->count(), 0);
    }

    public function autoRunFalseAllowsManualRun(): void
    {
        $this->ensureDriver();

        $this->transaction()->transact(
            callback: static function (EntityManagerInterface $em): void {
                $em->persist(new User('manual@example.com', 1));
                $em->run();
            },
            autoRun: false,
        );

        Assert::same($this->database()->table('user')->count(), 1);
    }

    public function openNewModePersistsRow(): void
    {
        $this->ensureDriver();

        $this->transaction()->transact(
            callback: static function (EntityManagerInterface $em): void {
                $em->persist(new User('opennew@example.com', 1));
            },
            emMode: TransactionMode::OpenNew,
        );

        Assert::same($this->database()->table('user')->count(), 1);
    }

    public function ignoreModePersistsRow(): void
    {
        $this->ensureDriver();

        $this->transaction()->transact(
            callback: static function (EntityManagerInterface $em): void {
                $em->persist(new User('ignore@example.com', 1));
            },
            emMode: TransactionMode::Ignore,
        );

        Assert::same($this->database()->table('user')->count(), 1);
    }

    public function resolvesDatabaseByEntityClass(): void
    {
        $this->ensureDriver();

        $this->transaction()->transact(
            callback: static function (EntityManagerInterface $em, DatabaseInterface $db): void {
                Assert::same($db->getName(), 'default');
            },
            source: User::class,
        );
    }

    public function rejectsEntityFromAnotherDriver(): void
    {
        $this->ensureDriver();

        // Post is mapped to the in-memory SQLite `secondary` connection.
        // Persisting it through a transaction scoped to the `default` driver must be rejected.
        try {
            $this->transaction()->transact(static function (EntityManagerInterface $em): void {
                $em->persist(new Post('cross-driver'));
            });
            Assert::fail('Expected a cross-driver TransactionException.');
        } catch (TransactionException $e) {
            Assert::string($e->getMessage())->contains('does not match the transaction database driver');
        }

        Assert::same($this->database()->table('user')->count(), 0);
    }

    public function deletesRowWithinTransaction(): void
    {
        $this->ensureDriver();

        $this->transaction()->transact(static function (EntityManagerInterface $em): void {
            $em->persist(new User('todelete@example.com', 1));
        });
        Assert::same($this->database()->table('user')->count(), 1);

        $user = $this->orm()->getRepository(User::class)->findOne();
        Assert::instanceOf($user, User::class);

        $this->transaction()->transact(static function (EntityManagerInterface $em) use ($user): void {
            $em->delete($user);
        });

        Assert::same($this->database()->table('user')->count(), 0);
    }

    public function updatesRowWithinTransaction(): void
    {
        $this->ensureDriver();

        $this->transaction()->transact(static function (EntityManagerInterface $em): void {
            $em->persist(new User('update@example.com', 1));
        });

        $user = $this->orm()->getRepository(User::class)->findOne();
        Assert::instanceOf($user, User::class);

        $this->transaction()->transact(static function (EntityManagerInterface $em) use ($user): void {
            $user->balance = 999;
            $em->persist($user);
        });

        $row = $this->database()->table('user')->select()->run()->fetch();
        Assert::same((int) $row['balance'], 999);
    }

    public function exceptionFromCallbackPropagates(): void
    {
        $this->ensureDriver();

        try {
            $this->transaction()->transact(static function (EntityManagerInterface $em): void {
                $em->persist(new User('nope@example.com', 1));
                throw new \RuntimeException('explicit');
            });
            Assert::fail('Expected RuntimeException to propagate.');
        } catch (\RuntimeException $e) {
            Assert::same($e->getMessage(), 'explicit');
        }

        Assert::same($this->database()->table('user')->count(), 0);
    }

    protected function orm(): ORMInterface
    {
        \assert($this->env !== null);
        return $this->env->orm;
    }
}
