<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\AnnotationsToAttributes\Rector\Class_\CoversAnnotationWithValueToAttributeRector;
use Rector\PHPUnit\AnnotationsToAttributes\Rector\ClassMethod\DataProviderAnnotationToAttributeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/core/tests/Drupal/Tests/Component',
    ])
    ->withSkip([
        __DIR__ . '/core/tests/Drupal/Tests/Component/Annotation/Doctrine/Fixtures',
        '*/ProxyClass/*',
        '*.api.php',
    ])
    ->withRules([
        CoversAnnotationWithValueToAttributeRector::class,
        DataProviderAnnotationToAttributeRector::class,
    ])
    ->withImportNames(
        importDocBlockNames: false,
        importShortClasses: false,
        removeUnusedImports: false,
    );
