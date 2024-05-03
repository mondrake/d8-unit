<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\AnnotationsToAttributes\Rector\ClassMethod\DataProviderAnnotationToAttributeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\PHPUnit\AnnotationsToAttributes\Rector\ClassMethod\TestWithAnnotationToAttributeRector;
use Rector\PHPUnit\AnnotationsToAttributes\Rector\Class_\AnnotationWithValueToAttributeRector;
use Rector\Php52\Rector\Property\VarToPublicPropertyRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/composer',
        __DIR__ . '/core',
    ])
    ->withSkipPath(
        __DIR__ . '/core/tests/Drupal/Tests/Component/Annotation/Doctrine/Fixtures',
    )
    ->withSkipPath(
        '*.api.php',
    )
    ->withRules([
//        AddVoidReturnTypeWhereNoReturnRector::class,
        DataProviderAnnotationToAttributeRector::class,
//        TestWithAnnotationToAttributeRector::class,
//        VarToPublicPropertyRector::class,
//        Rector\PHPUnit\AnnotationsToAttributes\Rector\Class_\AnnotationWithValueToAttributeRector
    ])
    ->withImportNames(
        importDocBlockNames: false,
        importShortClasses: false,
        removeUnusedImports: false,
    );
