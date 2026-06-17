<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Fixtures;

use Cycle\ORM\SchemaInterface;

/**
 * Shared ORM schema definition for the test entities.
 *
 * `User` is mapped to the `default` database, `Post` to the `secondary` database, so the two
 * entities resolve to different driver connections.
 */
final class TestSchema
{
    /**
     * @param non-empty-string $userDatabase
     * @param non-empty-string $postDatabase
     */
    public static function definition(
        string $userDatabase = 'default',
        string $postDatabase = 'secondary',
    ): array {
        return [
            'user' => [
                SchemaInterface::ENTITY => User::class,
                SchemaInterface::DATABASE => $userDatabase,
                SchemaInterface::TABLE => 'user',
                SchemaInterface::PRIMARY_KEY => 'id',
                SchemaInterface::COLUMNS => ['id', 'email', 'balance'],
                SchemaInterface::TYPECAST => ['id' => 'int', 'balance' => 'int'],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [],
            ],
            'post' => [
                SchemaInterface::ENTITY => Post::class,
                SchemaInterface::DATABASE => $postDatabase,
                SchemaInterface::TABLE => 'post',
                SchemaInterface::PRIMARY_KEY => 'id',
                SchemaInterface::COLUMNS => ['id', 'title'],
                SchemaInterface::TYPECAST => ['id' => 'int'],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [],
            ],
        ];
    }
}
