<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Unit;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\ORMInterface;
use Cycle\Transaction\Internal\TransactionImpl;
use Cycle\Transaction\Tests\Fixtures\SqliteEnvironment;
use Cycle\Transaction\Tests\Fixtures\TestEnvironment;
use Cycle\Transaction\Transaction;
use Testo\Assert;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

/**
 * Unit tests for the Yii3 DI configuration (`src/Bridge/Yii3/config/di.php`).
 *
 * The config is loaded into a real Yii container together with stub ORMInterface and
 * DatabaseProviderInterface services, and we assert that {@see Transaction} resolves to a shared
 * {@see TransactionImpl} instance.
 */
#[Test]
final class TransactionYiiConfigTest
{
    private TestEnvironment $env;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->env = SqliteEnvironment::create();
    }

    public function itResolvesTransactionAsASharedService(): void
    {
        /** @var array<string, mixed> $definitions */
        $definitions = require \dirname(__DIR__, 2) . '/src/Bridge/Yii3/config/di.php';

        $container = new Container(
            ContainerConfig::create()->withDefinitions([
                ORMInterface::class => $this->env->orm,
                DatabaseProviderInterface::class => $this->env->dbal,
                ...$definitions,
            ]),
        );

        $transaction = $container->get(Transaction::class);

        Assert::instanceOf($transaction, TransactionImpl::class);
        // The container must return the same shared instance on every resolution.
        Assert::same($container->get(Transaction::class), $transaction);
    }
}
