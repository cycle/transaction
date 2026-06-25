<?php

declare(strict_types=1);

namespace Cycle\Transaction\Bridge\Spiral\Bootloader;

use Cycle\Transaction\Internal\TransactionImpl;
use Cycle\Transaction\Transaction;
use Spiral\Boot\Bootloader\Bootloader;

final class TransactionBootloader extends Bootloader
{
    protected const SINGLETONS = [
        Transaction::class => TransactionImpl::class,
    ];
}
