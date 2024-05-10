<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\Reflection\ClassReflection;
use PHPUnit\Framework\TestCase;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\PhpAttribute\NodeFactory\PhpAttributeGroupFactory;
use Rector\PHPUnit\ValueObject\AnnotationWithValueToAttribute;
use Rector\Rector\AbstractRector;
use Rector\Reflection\ReflectionResolver;
use Rector\ValueObject\PhpVersionFeature;
use Rector\VersionBonding\Contract\MinPhpVersionInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

use Rector\Config\RectorConfig;

final class DrupalAnnotationToAttributeRector extends AbstractRector implements MinPhpVersionInterface
{
    private array $annotationTargets = [
        '@author' => [],
        '@backupGlobals' => [],
        '@backupStaticAttributes' => [],
        '@covers' => [],
        '@coversDefaultClass' => [],
        '@coversNothing' => [],
        '@dataProvider' => [],
        '@depends' => [],
        '@doesNotPerformAssertions' => [],
        '@group' => [],
        '@large' => [],
        '@medium' => [],
        '@preserveGlobalState' => [],
        '@requires' => [],
        '@runTestsInSeparateProcesses' => [],
        '@runInSeparateProcess' => [],
        '@small' => [],
        '@test' => [],
        '@testdox' => [],
        '@testWith' => [
            'multiline' => true,
        ],
        '@ticket' => [],
        '@uses' => [],
    ];

    private ?string $currentClassName;

    public function __construct(
        private readonly PhpDocTagRemover $phpDocTagRemover,
        private readonly PhpAttributeGroupFactory $phpAttributeGroupFactory,
        private readonly DocBlockUpdater $docBlockUpdater,
        private readonly PhpDocInfoFactory $phpDocInfoFactory,
        private readonly ReflectionResolver $reflectionResolver,
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Change annotations to attributes in Drupal test codebase', []);
    }

    public function getNodeTypes(): array
    {
        return [Class_::class, ClassMethod::class];
    }

    public function provideMinPhpVersion(): int
    {
        return PhpVersionFeature::ATTRIBUTES;
    }

    /**
     * @param Class_|ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        $nodeName = $this->getName($node);

        if ($node instanceof Class_) {
            $classReflection = $this->reflectionResolver->resolveClassReflection($node);
            if (! $classReflection instanceof ClassReflection) {
                return null;
            }

            if (! $classReflection->isClass()) {
                return null;
            }

            if (! $classReflection->isSubclassOf(TestCase::class)) {
                $this->currentClassName = null;
                return null;
            }

            $this->currentClassName = $nodeName;
        }
        
        if ($this->currentClassName === null) {
            return null;
        }

        if (! $phpDocInfo instanceof PhpDocInfo) {
            return null;
        }

        $phpDocInfo = $this->phpDocInfoFactory->createFromNode($node);
        if (! $phpDocInfo instanceof PhpDocInfo) {
            return null;
        }

        $hasChanged = false;

        foreach ($this->annotationTargets as $target => $targetConfig) {
            
            /** @var PhpDocTagNode[] $desiredTagValueNodes */
            $desiredTagValueNodes = $phpDocInfo->getTagsByName($target);

            foreach ($desiredTagValueNodes as $desiredTagValueNode) {
                if (! $desiredTagValueNode->value instanceof GenericTagValueNode) {
                    continue;
                }

                $attributeValue = $this->resolveAttributeValue($desiredTagValueNode->value, []);
                $attributeValueLines = count(explode("\n", $attributeValue));

                if ((! $targetConfig['multiline'] ?? false) && ($attributeValueLines > 1)) {
dump([$this->$currentClassName, $nodeName, $target, $targetConfig, ($targetConfig['multiline'] ?? false), $attributeValue]);
                }

/*                $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
                    $target->getAttributeClass(),
                    [$attributeValue]
                );

                $node->attrGroups[] = $attributeGroup;

                // cleanup
                $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);
                $hasChanged = true;*/
            }
        }
return null;
        if ($hasChanged) {
            $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);
            return $node;
        }

        return null;
    }

    private function resolveAttributeValue(
        GenericTagValueNode $genericTagValueNode,
        array $valueMap = [],
    ): mixed {
        if ($valueMap === []) {
            // no map? convert value as it is
            return $genericTagValueNode->value;
        }

        $originalValue = strtolower($genericTagValueNode->value);
        return $valueMap[$originalValue];
    }
}

return RectorConfig::configure()
    ->withPaths([
#        __DIR__ . '/core/tests/Drupal/Tests',
    #    __DIR__ . '/composer',
        __DIR__ . '/core/tests',
    ])
    ->withSkip([
        __DIR__ . '/core/tests/Drupal/Tests/Component/Annotation/Doctrine/Fixtures',
        '*/ProxyClass/*',
        '*.api.php',
    ])
    ->withRules([
        DrupalAnnotationToAttributeRector::class,
    ])
    ->withoutParallel()
    ->withImportNames(
        importDocBlockNames: false,
        importShortClasses: false,
        removeUnusedImports: false,
    );
