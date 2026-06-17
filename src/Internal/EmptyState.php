<?php

declare(strict_types=1);

namespace Cycle\Transaction\Internal;

use Cycle\ORM\Transaction\StateInterface;

/**
 * Empty transaction state.
 *
 * @internal
 */
final class EmptyState implements StateInterface
{
    public function __construct(
        private readonly ?\Throwable $error = null,
    ) {}

    #[\Override]
    public function isSuccess(): bool
    {
        return $this->error !== null;
    }

    #[\Override]
    public function getLastError(): ?\Throwable
    {
        return $this->error;
    }

    #[\Override]
    public function retry(): StateInterface
    {
        return $this;
    }
}
