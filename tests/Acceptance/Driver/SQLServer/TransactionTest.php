<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Acceptance\Driver\SQLServer;

use Cycle\Database\Config\SQLServer\DsnConnectionConfig;
use Cycle\Database\Config\SQLServerDriverConfig;
use Cycle\Transaction\Internal\TransactionImpl;
use Cycle\Transaction\Tests\Acceptance\Common\TransactionTestCase;
use Testo\Codecov\Covers;
use Testo\Filter\Group;
use Testo\Test;

#[Test]
#[Group('driver-sqlserver')]
#[Covers(TransactionImpl::class)]
final class TransactionTest extends TransactionTestCase
{
    protected function driverConfig(): SQLServerDriverConfig
    {
        return new SQLServerDriverConfig(
            connection: new DsnConnectionConfig(
                dsn: 'sqlsrv:Server=' . (\getenv('SQLSERVER_HOST') ?: '127.0.0.1')
                    . ',' . (\getenv('SQLSERVER_PORT') ?: '11433')
                    . ';Database=' . (\getenv('SQLSERVER_DATABASE') ?: 'tempdb')
                    . ';TrustServerCertificate=true',
                user: (string) (\getenv('SQLSERVER_USER') ?: 'SA'),
                password: (string) (\getenv('SQLSERVER_PASSWORD') ?: 'YourStrong!Passw0rd'),
            ),
            queryCache: true,
        );
    }
}
