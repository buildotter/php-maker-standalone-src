<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSkip([
        '*/tests/*',
        '*/Test/*',
        './vendor/jetbrains/phpstorm-stubs/*',
        './vendor/rector/*',
        './vendor/phpstan/*',
    ])
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/vendor',
    ])
    ->withDowngradeSets(php81: true);
