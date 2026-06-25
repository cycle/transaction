<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Unit;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\ORMInterface;
use Cycle\Transaction\Bridge\Spiral\Bootloader\TransactionBootloader;
use Cycle\Transaction\Internal\TransactionImpl;
use Cycle\Transaction\Tests\Fixtures\SqliteEnvironment;
use Cycle\Transaction\Tests\Fixtures\TestEnvironment;
use Cycle\Transaction\Transaction;
use Spiral\Core\Container;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * Unit tests for the Spiral {@see TransactionBootloader}.
 *
 * Rather than booting a full Spiral application, this checks the bootloader's contract directly: it
 * binds {@see Transaction} as a singleton resolving to {@see TransactionImpl}, given an
 * {@see ORMInterface} and a {@see DatabaseProviderInterface} are available in the container.
 */
#[Test]
#[Covers(TransactionBootloader::class)]
final class TransactionBootloaderTest
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
        $container->bindSingleton(ORMInterface::class, $this->env->orm);
        $container->bindSingleton(DatabaseProviderInterface::class, $this->env->dbal);

        // Apply the bootloader's bindings exactly as Spiral would.
        foreach ((new TransactionBootloader())->defineSingletons() as $alias => $resolver) {
            $container->bindSingleton($alias, $resolver);
        }

        $transaction = $container->get(Transaction::class);

        Assert::instanceOf($transaction, TransactionImpl::class);
        // A singleton must hand back the very same instance on every resolution.
        Assert::same($container->get(Transaction::class), $transaction);
    }
}
