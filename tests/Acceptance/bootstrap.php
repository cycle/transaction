<?php

declare(strict_types=1);

use Cycle\Database\Config;
use Cycle\Transaction\Tests\Acceptance\Common\BaseTestCase;

/**
 * Acceptance test bootstrap.
 *
 * Registers a driver configuration per supported database. The active set is controlled by the
 * `DB` environment variable (comma-separated list), mirroring the Cycle ORM integration suite:
 *
 *   DB=sqlite            vendor/bin/testo --suite=Acceptance
 *   DB=sqlite,postgres   vendor/bin/testo --suite=Acceptance
 *
 * When `DB` is unset, every driver is enabled. A per-driver test class throws {@see SkipTest}
 * when its driver is not part of the active set or the connection is unavailable, so running the
 * suite locally with only SQLite simply skips the server-backed drivers.
 */

$drivers = [
    'sqlite' => static fn(): Config\SQLiteDriverConfig => new Config\SQLiteDriverConfig(
        connection: new Config\SQLite\MemoryConnectionConfig(),
        queryCache: true,
    ),
    'mysql' => static fn(): Config\MySQLDriverConfig => new Config\MySQLDriverConfig(
        connection: new Config\MySQL\TcpConnectionConfig(
            database: (string) (\getenv('MYSQL_DATABASE') ?: 'spiral'),
            host: (string) (\getenv('MYSQL_HOST') ?: '127.0.0.1'),
            port: (int) (\getenv('MYSQL_PORT') ?: 13306),
            charset: 'utf8mb4',
            user: (string) (\getenv('MYSQL_USER') ?: 'root'),
            password: (string) (\getenv('MYSQL_PASSWORD') ?: 'YourStrong!Passw0rd'),
        ),
        queryCache: true,
    ),
    'postgres' => static fn(): Config\PostgresDriverConfig => new Config\PostgresDriverConfig(
        connection: new Config\Postgres\TcpConnectionConfig(
            database: (string) (\getenv('POSTGRES_DATABASE') ?: 'spiral'),
            host: (string) (\getenv('POSTGRES_HOST') ?: '127.0.0.1'),
            port: (int) (\getenv('POSTGRES_PORT') ?: 15432),
            user: (string) (\getenv('POSTGRES_USER') ?: 'postgres'),
            password: (string) (\getenv('POSTGRES_PASSWORD') ?: 'YourStrong!Passw0rd'),
        ),
        schema: 'public',
        queryCache: true,
    ),
    'sqlserver' => static fn(): Config\SQLServerDriverConfig => new Config\SQLServerDriverConfig(
        connection: new Config\SQLServer\DsnConnectionConfig(
            dsn: 'sqlsrv:Server=' . (\getenv('SQLSERVER_HOST') ?: '127.0.0.1')
                . ',' . (\getenv('SQLSERVER_PORT') ?: '11433')
                . ';Database=' . (\getenv('SQLSERVER_DATABASE') ?: 'tempdb')
                . ';TrustServerCertificate=true',
            user: (string) (\getenv('SQLSERVER_USER') ?: 'SA'),
            password: (string) (\getenv('SQLSERVER_PASSWORD') ?: 'YourStrong!Passw0rd'),
        ),
        queryCache: true,
    ),
];

$selected = \getenv('DB');
$active = $selected === false || $selected === ''
    ? \array_keys($drivers)
    : \array_map('trim', \explode(',', $selected));

BaseTestCase::$drivers = $drivers;
BaseTestCase::$activeDrivers = \array_values(\array_intersect(\array_keys($drivers), $active));
