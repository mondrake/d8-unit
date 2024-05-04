<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Reflection\ReflectionResolver;
use Rector\Php80\NodeAnalyzer\PhpAttributeAnalyzer;
use Rector\PhpAttribute\NodeFactory\PhpAttributeGroupFactory;
use Rector\PHPUnit\NodeAnalyzer\TestsNodeAnalyzer;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\BrowserTestBase;
use Rector\Config\RectorConfig;

final class FillRunTestInIsolationRector extends AbstractRector
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly PhpAttributeGroupFactory $phpAttributeGroupFactory,
        private readonly PhpAttributeAnalyzer $phpAttributeAnalyzer,
        private readonly TestsNodeAnalyzer $testsNodeAnalyzer,
        private readonly ReflectionResolver $reflectionResolver,
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Adds `#[RunTestsInSeparateProcesses]` attribute to Kernel and Functional tests.', []);
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        $className = $this->getName($node);

        if ($className === null) {
            return null;
        }

        $classReflection = $this->reflectionResolver->resolveClassReflection($node);
        if (! $classReflection instanceof ClassReflection) {
            return null;
        }

        if (! $classReflection->isClass()) {
            return null;
        }

        if (! $classReflection->isSubclassOf(KernelTestBase::class) && ! $classReflection->isSubclassOf(BrowserTestBase::class)) {
            return null;
        }

        if ($this->phpAttributeAnalyzer->hasPhpAttributes($node, [
            'PHPUnit\\Framework\\Attributes\\RunTestsInSeparateProcesses',
        ])) {
            return null;
        }

        $coversAttributeGroup = $this->createAttributeGroup();

        $node->attrGroups = array_merge($node->attrGroups, [$coversAttributeGroup]);

        return $node;
    }

    private function createAttributeGroup(): AttributeGroup
    {
        $attributeClass = 'PHPUnit\\Framework\\Attributes\\RunTestsInSeparateProcesses';

        return $this->phpAttributeGroupFactory->createFromClassWithItems($attributeClass, []);
    }
}

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
        FillRunTestInIsolationRector::class,
    ])
    ->withImportNames(
        importDocBlockNames: false,
        importShortClasses: false,
        removeUnusedImports: false,
    );
