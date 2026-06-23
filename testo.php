<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;

return new ApplicationConfig(
    src: ['src'],
    suites: [
        new SuiteConfig(name: 'Unit', location: ['tests/Unit']),
        // Acceptance tests run against real databases. The abstract base classes live in
        // `tests/Acceptance/Common` and are extended by per-driver concrete classes under
        // `tests/Acceptance/Driver`. Only the concrete subclasses are discovered as test cases.
        new SuiteConfig(
            name: 'Acceptance',
            location: new FinderConfig(include: ['tests/Acceptance/Driver']),
        ),
    ],
);
