<?php

declare(strict_types=1);

use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use RectorLaravel\Rector\Class_\TablePropertyToTableAttributeRector;
use RectorLaravel\Set\LaravelLevelSetList;

return RectorConfig::configure()
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_130,
        LevelSetList::UP_TO_PHP_85,
        PestSetList::CODING_STYLE,
    ])
    ->withSkip([
        TablePropertyToTableAttributeRector::class,
        // Rector cannot reflect dynamic Eloquent model properties in these
        // model-heavy tests; skip so the Pest coding-style rules run on the rest.
        __DIR__.'/tests/Unit/Services/Roles/OptInRoleServiceTest.php',
        __DIR__.'/tests/Unit/Services/Roles/OnRequestRoleServiceTest.php',
        __DIR__.'/tests/Unit/Observers/CharacterAffiliationObserverTest.php',
    ])
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/database',
    ]);
