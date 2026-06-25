<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Unit;

use Cycle\Transaction\TransactionMode;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(TransactionMode::class)]
final class TransactionModeTest
{
    public function exposesFourCases(): void
    {
        $cases = TransactionMode::cases();

        Assert::count($cases, 4);
    }

    public function hasExpectedNames(): void
    {
        $names = \array_map(static fn(TransactionMode $m): string => $m->name, TransactionMode::cases());

        Assert::same($names, ['Ignore', 'Current', 'OpenNew', 'Exclusive']);
    }

    public function isPureEnumWithoutBackingValue(): void
    {
        // A non-backed enum must not be a BackedEnum. This guards against an accidental
        // change of the enum kind which would alter serialization semantics.
        Assert::false(TransactionMode::Ignore instanceof \BackedEnum);
    }
}
