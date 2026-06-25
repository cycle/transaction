<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Unit;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\ORMInterface;
use Cycle\Transaction\Bridge\Laravel\Providers\TransactionServiceProvider;
use Cycle\Transaction\Internal\TransactionImpl;
use Cycle\Transaction\Tests\Fixtures\SqliteEnvironment;
use Cycle\Transaction\Tests\Fixtures\TestEnvironment;
use Cycle\Transaction\Transaction;
use Illuminate\Container\Container;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * Unit tests for the Laravel {@see TransactionServiceProvider}.
 *
 * Rather than booting a full Laravel application, this checks the provider's contract directly: it
 * binds {@see Transaction} as a singleton resolving to {@see TransactionImpl}, given an
 * {@see ORMInterface} and a {@see DatabaseProviderInterface} are available in the container.
 */
#[Test]
#[Covers(TransactionServiceProvider::class)]
final class TransactionServiceProviderTest
{
    private TestEnvironment $env;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->env = SqliteEnvironment::create();
    }

    public function itResolvesTransactionAsASingleton(): void
    {
        $container = new Container();
        $container->instance(ORMInterface::class, $this->env->orm);
        $container->instance(DatabaseProviderInterface::class, $this->env->dbal);

        // ServiceProvider's docblock types $app as the Application contract, but it only uses the
        // container's bind/singleton methods, which Illuminate\Container\Container provides.
        /** @psalm-suppress InvalidArgument */
        (new TransactionServiceProvider($container))->register();

        $transaction = $container->make(Transaction::class);

        Assert::instanceOf($transaction, TransactionImpl::class);
        // A singleton must hand back the very same instance on every resolution.
        Assert::same($container->make(Transaction::class), $transaction);
    }
}
