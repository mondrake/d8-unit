<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\AnnotationsToAttributes\Rector\ClassMethod\DataProviderAnnotationToAttributeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/composer',
        __DIR__ . '/core',
    ])
    ->withSkipPath(
        __DIR__ . '/core/tests/Drupal/Tests/Component/Annotation/Doctrine/Fixtures',
    )
    ->withSkipPath(
        '*/ProxyClass/*',
    )
    ->withSkipPath(
        '*.api.php',
    )
    ->withRules([
        DataProviderAnnotationToAttributeRector::class,
    ])
    ->withImportNames(
        importDocBlockNames: false,
        importShortClasses: false,
        removeUnusedImports: false,
    );
