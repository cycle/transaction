<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Acceptance\Driver\MySQL;

use Cycle\Database\Config\MySQL\TcpConnectionConfig;
use Cycle\Database\Config\MySQLDriverConfig;
use Cycle\Transaction\Internal\TransactionImpl;
use Cycle\Transaction\Tests\Acceptance\Common\TransactionTestCase;
use Testo\Codecov\Covers;
use Testo\Filter\Group;
use Testo\Test;

#[Test]
#[Group('driver-mysql')]
#[Covers(TransactionImpl::class)]
final class TransactionTest extends TransactionTestCase
{
    protected function driverConfig(): MySQLDriverConfig
    {
        return new MySQLDriverConfig(
            connection: new TcpConnectionConfig(
                database: (string) (\getenv('MYSQL_DATABASE') ?: 'spiral'),
                host: (string) (\getenv('MYSQL_HOST') ?: '127.0.0.1'),
                port: (int) (\getenv('MYSQL_PORT') ?: 13306),
                charset: 'utf8mb4',
                user: (string) (\getenv('MYSQL_USER') ?: 'root'),
                password: (string) (\getenv('MYSQL_PASSWORD') ?: 'YourStrong!Passw0rd'),
            ),
            queryCache: true,
        );
    }
}
