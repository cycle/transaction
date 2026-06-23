<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Acceptance\Driver\Postgres;

use Cycle\Database\Config\Postgres\TcpConnectionConfig;
use Cycle\Database\Config\PostgresDriverConfig;
use Cycle\Transaction\Internal\TransactionImpl;
use Cycle\Transaction\Tests\Acceptance\Common\TransactionTestCase;
use Testo\Codecov\Covers;
use Testo\Filter\Group;
use Testo\Test;

#[Test]
#[Group('driver-postgres')]
#[Covers(TransactionImpl::class)]
final class TransactionTest extends TransactionTestCase
{
    protected function driverConfig(): PostgresDriverConfig
    {
        return new PostgresDriverConfig(
            connection: new TcpConnectionConfig(
                database: (string) (\getenv('POSTGRES_DATABASE') ?: 'spiral'),
                host: (string) (\getenv('POSTGRES_HOST') ?: '127.0.0.1'),
                port: (int) (\getenv('POSTGRES_PORT') ?: 15432),
                user: (string) (\getenv('POSTGRES_USER') ?: 'postgres'),
                password: (string) (\getenv('POSTGRES_PASSWORD') ?: 'YourStrong!Passw0rd'),
            ),
            schema: 'public',
            queryCache: true,
        );
    }
}
