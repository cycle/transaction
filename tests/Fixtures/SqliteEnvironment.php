<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Fixtures;

use Cycle\Database\Config\SQLite\MemoryConnectionConfig;
use Cycle\Database\Config\SQLiteDriverConfig;

/**
 * Convenience factory for an all-SQLite (in-memory) environment used by Unit tests.
 */
final class SqliteEnvironment
{
    public static function create(): TestEnvironment
    {
        return new TestEnvironment(
            defaultDriver: new SQLiteDriverConfig(connection: new MemoryConnectionConfig()),
            secondaryDriver: new SQLiteDriverConfig(connection: new MemoryConnectionConfig()),
        );
    }
}
