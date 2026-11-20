<?php

declare(strict_types=1);

use Cycle\Testing\Config;
use Cycle\Testing\Database\Layout;
use Cycle\Testing\ORM\OrmPlugin;
use Cycle\Transaction\Tests\Fixtures\TestSchema;

return Config::forPackage('cycle/transaction', root: __DIR__)
    ->unit('tests/Unit')
    ->acceptance(
        'tests/Acceptance',
        # `Post` lives in `secondary`: a connection of another driver than the one under test.
        layout: Layout::withSqlite(['secondary']),
        plugins: [new OrmPlugin(schema: TestSchema::definition())],
    )
    ->build();
