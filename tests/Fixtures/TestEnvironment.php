<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Fixtures;

use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Config\DriverConfig;
use Cycle\Database\DatabaseManager;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\Factory;
use Cycle\ORM\ORM;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Schema;
use Cycle\Transaction\Internal\TransactionImpl;
use Cycle\Transaction\Transaction;

/**
 * Builds a ready-to-use ORM + DBAL environment with two databases (`default` and `secondary`)
 * and creates the physical `user` / `post` tables.
 *
 * Reused by both Unit tests (two SQLite connections) and Acceptance tests (a real driver as the
 * `default` connection plus an in-memory SQLite as the `secondary` one).
 */
final class TestEnvironment
{
    public readonly DatabaseManager $dbal;
    public readonly ORMInterface $orm;
    public readonly Transaction $transaction;

    public function __construct(
        DriverConfig $defaultDriver,
        DriverConfig $secondaryDriver,
    ) {
        $this->dbal = new DatabaseManager(new DatabaseConfig([
            'default' => 'default',
            'databases' => [
                'default' => ['connection' => 'default'],
                'secondary' => ['connection' => 'secondary'],
            ],
            'connections' => [
                'default' => $defaultDriver,
                'secondary' => $secondaryDriver,
            ],
        ]));

        $this->orm = new ORM(
            new Factory($this->dbal),
            new Schema(TestSchema::definition()),
        );

        $this->transaction = new TransactionImpl($this->orm, $this->dbal);

        $this->createTables();
    }

    public function entityManager(): EntityManagerInterface
    {
        return new \Cycle\ORM\EntityManager($this->orm);
    }

    public function dbal(): DatabaseProviderInterface
    {
        return $this->dbal;
    }

    public function dropTables(): void
    {
        $this->dropTable('default', 'user');
        $this->dropTable('secondary', 'post');
    }

    private function createTables(): void
    {
        $userSchema = $this->dbal->database('default')->table('user')->getSchema();
        $userSchema->primary('id');
        $userSchema->string('email');
        $userSchema->integer('balance')->defaultValue(0);
        $userSchema->save();

        $postSchema = $this->dbal->database('secondary')->table('post')->getSchema();
        $postSchema->primary('id');
        $postSchema->string('title');
        $postSchema->save();
    }

    private function dropTable(string $database, string $table): void
    {
        $schema = $this->dbal->database($database)->table($table)->getSchema();
        if ($schema->exists()) {
            $schema->declareDropped();
            $schema->save();
        }
    }
}
