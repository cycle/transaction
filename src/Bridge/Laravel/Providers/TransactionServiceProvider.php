<?php

declare(strict_types=1);

namespace Cycle\Transaction\Bridge\Laravel\Providers;

use Cycle\Transaction\Internal\TransactionImpl;
use Cycle\Transaction\Transaction;
use Illuminate\Support\ServiceProvider;

final class TransactionServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        // TransactionImpl is autowired from the container's ORMInterface and DatabaseProviderInterface,
        // which the Cycle ORM Laravel adapter is expected to bind.
        $this->app->singleton(Transaction::class, TransactionImpl::class);
    }
}
