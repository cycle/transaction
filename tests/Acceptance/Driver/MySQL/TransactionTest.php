<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Acceptance\Driver\MySQL;

use Cycle\Transaction\Internal\TransactionImpl;
use Cycle\Transaction\Tests\Acceptance\Common\TransactionTestCase;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(TransactionImpl::class)]
final class TransactionTest extends TransactionTestCase
{
    public const DRIVER = 'mysql';
}
