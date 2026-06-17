<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Unit;

use Cycle\ORM\Transaction\StateInterface;
use Cycle\Transaction\Internal\EmptyState;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(EmptyState::class)]
final class EmptyStateTest
{
    public function implementsStateInterface(): void
    {
        Assert::instanceOf(new EmptyState(), StateInterface::class);
    }

    public function withoutErrorHasNoLastError(): void
    {
        $state = new EmptyState();

        Assert::null($state->getLastError());
    }

    public function returnsProvidedError(): void
    {
        $error = new \RuntimeException('failed');
        $state = new EmptyState($error);

        Assert::same($state->getLastError(), $error);
    }

    public function retryReturnsItself(): void
    {
        $state = new EmptyState();

        Assert::same($state->retry(), $state);
    }

    public function retryReturnsItselfWhenCarryingError(): void
    {
        $state = new EmptyState(new \RuntimeException('failed'));

        Assert::same($state->retry(), $state);
    }

    public function isSuccessReflectsPresenceOfError(): void
    {
        // Documents the current implementation: isSuccess() returns ($error !== null).
        Assert::false((new EmptyState())->isSuccess());
        Assert::true((new EmptyState(new \RuntimeException('e')))->isSuccess());
    }
}
