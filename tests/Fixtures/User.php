<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Fixtures;

/**
 * Plain ORM entity used across Unit and Acceptance tests.
 *
 * Must not be `final` and must not use `readonly` properties — the default Cycle mapper
 * builds a proxy by extending the class and hydrates via reflection after construction.
 */
class User
{
    public ?int $id = null;
    public string $email = '';
    public int $balance = 0;

    public function __construct(string $email = '', int $balance = 0)
    {
        $this->email = $email;
        $this->balance = $balance;
    }
}
