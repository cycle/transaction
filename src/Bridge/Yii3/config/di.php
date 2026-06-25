<?php

declare(strict_types=1);

use Cycle\Transaction\Internal\TransactionImpl;
use Cycle\Transaction\Transaction;

/**
 * Yii3 DI definitions. {@see TransactionImpl} is autowired from the container's ORMInterface and
 * DatabaseProviderInterface, which the Yii Cycle integration is expected to register.
 */
return [
    Transaction::class => TransactionImpl::class,
];
