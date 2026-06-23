<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Acceptance\Common;

use Cycle\Database\Config\DriverConfig;
use Cycle\Database\Config\SQLite\MemoryConnectionConfig;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\Exception\DBALException;
use Cycle\Transaction\Tests\Fixtures\TestEnvironment;
use Cycle\Transaction\Transaction;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;

/**
 * Base class for acceptance tests executed against a real database driver.
 *
 * The driver under test is provided by the concrete per-driver subclass through {@see driverConfig()};
 * which drivers run is decided entirely by inheritance — a subclass exists under `tests/Acceptance/Driver`
 * only for the drivers a given test case supports, and the matching `#[Group('driver-*')]` selects it at
 * run time (see the `test:*` composer scripts). There is no runtime "is this driver enabled" guard:
 * if a subclass is collected, its driver is expected to be available (CI boots it via docker-compose).
 *
 * Testo skips abstract classes during discovery but collects inherited public methods of concrete
 * classes, so every test method defined in {@see TransactionTestCase} runs once per concrete subclass.
 *
 * The driver under test is wired as the `default` database connection; an in-memory SQLite connection
 * is always used for the `secondary` database so cross-driver behaviour can be checked.
 */
abstract class BaseTestCase
{
    protected ?TestEnvironment $env = null;

    /**
     * Driver configuration for the `default` connection of the concrete subclass.
     */
    abstract protected function driverConfig(): DriverConfig;

    #[BeforeTest]
    public function bootEnvironment(): void
    {
        $this->env = new TestEnvironment(
            defaultDriver: $this->driverConfig(),
            secondaryDriver: new SQLiteDriverConfig(connection: new MemoryConnectionConfig()),
        );
    }

    #[AfterTest]
    public function shutdownEnvironment(): void
    {
        if ($this->env === null) {
            return;
        }

        try {
            $this->env->dropTables();
        } catch (DBALException) {
            // Ignore cleanup errors — the next run recreates the schema.
        }

        $this->env = null;
    }

    protected function transaction(): Transaction
    {
        \assert($this->env !== null);
        return $this->env->transaction;
    }

    protected function database(string $name = 'default'): DatabaseInterface
    {
        \assert($this->env !== null);
        return $this->env->dbal->database($name);
    }
}
