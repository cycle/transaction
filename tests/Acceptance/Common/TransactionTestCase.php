<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Acceptance\Common;

use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Cycle\Transaction\Exception\TransactionException;
use Cycle\Transaction\FlushMode;
use Cycle\Transaction\Tests\Fixtures\Post;
use Cycle\Transaction\Tests\Fixtures\User;
use Cycle\Transaction\TransactionMode;
use Testo\Assert;
use Testo\Filter\Group;

/**
 * Acceptance scenarios for {@see \Cycle\Transaction\Transaction} executed against a real database.
 *
 * Abstract: discovered and run only through the concrete per-driver subclasses under
 * `tests/Acceptance/Driver`. Each subclass supplies its driver via {@see BaseTestCase::driverConfig()};
 * {@see BaseTestCase::bootEnvironment()} boots the environment before every test. Which drivers a test
 * case runs on is expressed purely by inheritance — a subclass exists only for the supported drivers.
 */
#[Group('driver')]
abstract class TransactionTestCase extends BaseTestCase
{
    public function commitsInsertedRow(): void
    {
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
$this->transaction()->transact(static function (EntityManagerInterface $em): void {
            $em->persist(new User('a@example.com', 1));
            $em->persist(new User('b@example.com', 2));
            $em->persist(new User('c@example.com', 3));
        });

        Assert::same($this->database()->table('user')->count(), 3);
    }

    public function rollsBackOnExceptionInCallback(): void
    {
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
$result = $this->transaction()->transact(
            static fn(EntityManagerInterface $em): string => 'done',
        );

        Assert::same($result, 'done');
    }

    public function providesDatabaseAndEntityManager(): void
    {
$this->transaction()->transact(function (EntityManagerInterface $em, DatabaseInterface $db): void {
            Assert::instanceOf($em, EntityManagerInterface::class);
            Assert::same($db->getName(), 'default');
            // The transaction must be open inside the callback.
            Assert::true($db->getDriver()->getTransactionLevel() > 0);
        });
    }

    public function dataSurvivesSequentialTransactions(): void
    {
$this->transaction()->transact(static function (EntityManagerInterface $em): void {
            $em->persist(new User('first@example.com', 10));
        });

        $this->transaction()->transact(static function (EntityManagerInterface $em): void {
            $em->persist(new User('second@example.com', 20));
        });

        Assert::same($this->database()->table('user')->count(), 2);
    }

    public function failOnPendingRejectsPendingChanges(): void
    {
try {
            $this->transaction()->transact(
                callback: static function (EntityManagerInterface $em): void {
                    $em->persist(new User('pending@example.com', 1));
                },
                flush: FlushMode::FailOnPending,
            );
            Assert::fail('Expected TransactionException for pending changes.');
        } catch (TransactionException $e) {
            Assert::same($e->getMessage(), 'Entity Manager has pending changes.');
        }

        // The aborted transaction must have rolled back.
        Assert::same($this->database()->table('user')->count(), 0);
    }

    public function failOnPendingAllowsManualRun(): void
    {
$this->transaction()->transact(
            callback: static function (EntityManagerInterface $em): void {
                $em->persist(new User('manual@example.com', 1));
                $em->run();
            },
            flush: FlushMode::FailOnPending,
        );

        Assert::same($this->database()->table('user')->count(), 1);
    }

    public function onWriteFlushPersistsWithoutManualRun(): void
    {
$this->transaction()->transact(
            callback: static function (EntityManagerInterface $em): void {
                $em->persist(new User('onwrite@example.com', 1));
            },
            flush: FlushMode::OnWrite,
        );

        Assert::same($this->database()->table('user')->count(), 1);
    }

    public function skipPendingDiscardsUnflushedChanges(): void
    {
$this->transaction()->transact(
            callback: static function (EntityManagerInterface $em): void {
                $em->persist(new User('skipped@example.com', 1));
                // Left unflushed: SkipPending commits the DBAL transaction and drops it.
            },
            flush: FlushMode::SkipPending,
        );

        Assert::same($this->database()->table('user')->count(), 0);
    }

    public function openNewModePersistsRow(): void
    {
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
$this->transaction()->transact(
            callback: static function (EntityManagerInterface $em, DatabaseInterface $db): void {
                Assert::same($db->getName(), 'default');
            },
            source: User::class,
        );
    }

    public function rejectsEntityFromAnotherDriver(): void
    {
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
