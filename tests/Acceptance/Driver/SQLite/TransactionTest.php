<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Acceptance\Driver\SQLite;

use Cycle\Database\Config\SQLite\MemoryConnectionConfig;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Transaction\Internal\TransactionImpl;
use Cycle\Transaction\Tests\Acceptance\Common\TransactionTestCase;
use Testo\Codecov\Covers;
use Testo\Filter\Group;
use Testo\Test;

#[Test]
#[Group('driver-sqlite')]
#[Covers(TransactionImpl::class)]
final class TransactionTest extends TransactionTestCase
{
    protected function driverConfig(): SQLiteDriverConfig
    {
        return new SQLiteDriverConfig(
            connection: new MemoryConnectionConfig(),
            queryCache: true,
        );
    }
}
