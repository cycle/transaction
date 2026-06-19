<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Acceptance\Driver\SQLServer;

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
    public const DRIVER = 'sqlserver';
}
