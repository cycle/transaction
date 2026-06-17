<?php

declare(strict_types=1);

namespace Cycle\Transaction\Tests\Fixtures;

/**
 * Entity mapped to a different database connection than {@see User}.
 * Used to verify cross-driver validation in the scoped Entity Manager.
 */
class Post
{
    public ?int $id = null;
    public string $title = '';

    public function __construct(string $title = '')
    {
        $this->title = $title;
    }
}
