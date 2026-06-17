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
use Testo\Core\Exception\SkipTest;
use Testo\Lifecycle\AfterTest;

/**
 * Base class for acceptance tests executed against a real database driver.
 *
 * Concrete per-driver subclasses (e.g. {@see \Cycle\Transaction\Tests\Acceptance\Driver\SQLite})
 * declare the {@see DRIVER} constant and carry the class-level `#[Test]` attribute. Because Testo
 * skips abstract classes during discovery but collects inherited public methods of concrete
 * classes, every test method defined here runs once per concrete driver subclass.
 *
 * The driver under test is wired as the `default` database connection; an in-memory SQLite
 * connection is always used for the `secondary` database so cross-driver behaviour can be checked.
 *
 * Note: a test that cannot run for the current driver throws {@see SkipTest} from {@see ensureDriver()},
 * which must be called from the test body (not a lifecycle hook) so Testo reports it as *Skipped*
 * rather than *Aborted*. Every test calls {@see ensureDriver()} as its first statement.
 */
abstract class BaseTestCase
{
    /** Driver name for the current subclass; overridden by concrete classes. */
    public const DRIVER = null;

    /**
     * Driver config factories keyed by driver name. Populated by the acceptance bootstrap.
     *
     * @var array<non-empty-string, callable(): DriverConfig>
     */
    public static array $drivers = [];

    /**
     * Driver names enabled for this run (filtered by the `DB` env variable).
     *
     * @var list<non-empty-string>
     */
    public static array $activeDrivers = [];

    protected ?TestEnvironment $env = null;

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

    /**
     * Boot the environment for the current driver or skip the test.
     *
     * Must be invoked from the test body. Throwing {@see SkipTest} here yields a *Skipped*
     * verdict; the same throw from a `#[BeforeTest]` hook would be reported as *Aborted*.
     */
    protected function ensureDriver(): void
    {
        $driver = static::DRIVER;

        if ($driver === null) {
            throw new SkipTest('No DRIVER defined for ' . static::class);
        }

        if (!\in_array($driver, self::$activeDrivers, true)) {
            throw new SkipTest(\sprintf('Driver `%s` is not enabled (set DB=%s to run).', $driver, $driver));
        }

        if (!isset(self::$drivers[$driver])) {
            throw new SkipTest(\sprintf('No configuration registered for driver `%s`.', $driver));
        }

        if ($this->env !== null) {
            return;
        }

        try {
            $env = new TestEnvironment(
                defaultDriver: (self::$drivers[$driver])(),
                secondaryDriver: new SQLiteDriverConfig(connection: new MemoryConnectionConfig()),
            );
            // Force a connection so unavailable servers are reported as skips, not failures.
            $env->dbal->database('default')->getDriver()->connect();
        } catch (\Throwable $e) {
            throw new SkipTest(\sprintf('Driver `%s` is not available: %s', $driver, $e->getMessage()));
        }

        $this->env = $env;
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
