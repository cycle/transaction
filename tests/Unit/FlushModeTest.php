<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Unit;

use Cycle\Transaction\FlushMode;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(FlushMode::class)]
final class FlushModeTest
{
    public function exposesFourCases(): void
    {
        $cases = FlushMode::cases();

        Assert::count($cases, 4);
    }

    public function hasExpectedNames(): void
    {
        $names = \array_map(static fn(FlushMode $m): string => $m->name, FlushMode::cases());

        Assert::same($names, ['OnWrite', 'BeforeCommit', 'FailOnPending', 'SkipPending']);
    }

    public function isPureEnumWithoutBackingValue(): void
    {
        // A non-backed enum must not be a BackedEnum. This guards against an accidental
        // change of the enum kind which would alter serialization semantics.
        Assert::false(FlushMode::OnWrite instanceof \BackedEnum);
    }
}
