<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Unit;

use Cycle\Transaction\Exception\TransactionException;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(TransactionException::class)]
final class TransactionExceptionTest
{
    public function isRuntimeException(): void
    {
        $exception = new TransactionException('boom');

        Assert::instanceOf($exception, \RuntimeException::class);
    }

    public function keepsMessageAndCode(): void
    {
        $exception = new TransactionException('something went wrong', 42);

        Assert::same($exception->getMessage(), 'something went wrong');
        Assert::same($exception->getCode(), 42);
    }

    public function keepsPreviousException(): void
    {
        $previous = new \LogicException('root cause');
        $exception = new TransactionException('wrapped', 0, $previous);

        Assert::same($exception->getPrevious(), $previous);
    }
}
