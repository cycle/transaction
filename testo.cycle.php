<?php

declare(strict_types=1);

use Cycle\Testing\Config;
use Cycle\Testing\Database\Layout;
use Cycle\Testing\ORM\OrmPlugin;

return Config::forPackage('cycle/transaction', root: __DIR__)
    ->unit('tests/Unit')
    ->feature(
        'tests/Feature',
        layout: Layout::withSqlite(['secondary']),
        plugins: [new OrmPlugin()],
    )
    ->build();
