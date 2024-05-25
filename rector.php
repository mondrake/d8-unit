<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeDumper;
use PhpParser\ParserFactory;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\Reflection\ClassReflection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
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
        '@after' => [],
        '@afterClass' => [],
        '@author' => [],
        '@backupGlobals' => [],
        '@backupStaticAttributes' => [],
        '@before' => [],
        '@beforeClass' => [],
        '@covers' => [
            'converter' => 'convertCovers',
        ],
        '@coversDefaultClass' => [
            'converter' => 'convertCoversDefaultClass',
        ],
        '@coversNothing' => [
            'converter' => 'convertCoversNothing',
        ],
        '@dataProvider' => [
            'converter' => 'convertDataProvider',
        ],
        '@depends' => [],
        '@doesNotPerformAssertions' => [],
        '@group' => [
            'converter' => 'convertGroup',
        ],
        '@large' => [],
        '@medium' => [
            'converter' => 'convertMedium',
        ],
        '@preserveGlobalState' => [
            'converter' => 'convertPreserveGlobalState',
        ],
        '@requires' => [
            'converter' => 'convertRequires',
        ],
        '@runTestsInSeparateProcesses' => [
            'converter' => 'convertRunTestsInSeparateProcesses',
        ],
        '@runInSeparateProcess' => [
            'converter' => 'convertRunInSeparateProcess',
        ],
        '@small' => [],
        '@test' => [],
        '@testdox' => [],
        '@testWith' => [
            'multiline' => true,
            'converter' => 'convertTestWith',
        ],
        '@ticket' => [],
        '@uses' => [
            'converter' => 'convertUses',
        ],
    ];

    private static ?string $currentClassName;
    private static ?Node $currentClassNode;

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
                self::$currentClassName = null;
                self::$currentClassNode = null;
                return null;
            }

            self::$currentClassName = $nodeName;
            self::$currentClassNode = $node;
        }

        if (! isset(self::$currentClassName)) {
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

                $targetConverter = $targetConfig['converter'] ?? false;

                if (! $targetConverter) {
                    throw new \RuntimeException('No converter for ' . $target . ' annotation');
                }

                if ((! $targetConfig['multiline'] ?? false) && ($attributeValueLines > 1)) {
                    throw new \RuntimeException('Unexepected multiline annotation value in ' . var_export([self::$currentClassName, $nodeName, $target, $attributeValue], true));
                }

                call_user_func([$this, $targetConverter], $node, $phpDocInfo, $desiredTagValueNode);
                $hasChanged = true;
            }
        }

        if ($hasChanged) {
            $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo(self::$currentClassNode);
            $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);
            return $node;
        }

        return null;
    }

    private function convertRunTestsInSeparateProcesses(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
            RunTestsInSeparateProcesses::class,
            [],
        );

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertRunInSeparateProcess(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
            RunInSeparateProcess::class,
            [],
        );

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertPreserveGlobalState(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {
        $preserve = match ($desiredTagValueNode->value->value) {
            'enabled' => true,
            'disabled' => false,
        };

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
            PreserveGlobalState::class,
            [$preserve],
        );

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertDataProvider(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
            DataProvider::class,
            [$desiredTagValueNode->value->value],
        );

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertGroup(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {
        $value = $desiredTagValueNode->value->value;

        $attributeGroup = match ($value) {
            '@legacy' => $this->phpAttributeGroupFactory->createFromClassWithItems(
                IndirectDeprecations::class,
                [],
            ),
            default => $this->phpAttributeGroupFactory->createFromClassWithItems(
                Group::class,
                [(string) $value],
            ),
        };

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertTestWith(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {
        $values = explode("\n", $desiredTagValueNode->value->value);

        foreach ($values as $value) {
            $this->parseTestWithData($value);
            $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
                TestWith::class,
                [$value],
            );
            $node->attrGroups[] = $attributeGroup;
        };

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertRequires(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {
        $value = explode(' ', $desiredTagValueNode->value->value);

        $attributeGroup = match ($value[0]) {
            'extension' => $this->phpAttributeGroupFactory->createFromClassWithItems(
                RequiresPhpExtension::class,
                [$value[1]],
            ),
            default => throw new \RuntimeException('Unsupported require "' . $value[0] . '"'),
        };

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }
    
    private function convertCoversDefaultClass(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $classLikeName = $desiredTagValueNode->value->value;
        $classLikeName = \ltrim($classLikeName, '\\');
        $fullyQualified = new FullyQualified($classLikeName);
        $classConst = new ClassConstFetch($fullyQualified, 'class');
        
        $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
            CoversClass::class,
            [$classConst],
        );

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertCovers(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $legacyCoversValueNode = new PhpDocTagNode('@legacy-covers', $desiredTagValueNode->value);
        $phpDocInfo->addPhpDocTagNode($legacyCoversValueNode);
        
        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertCoversNothing(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
            CoversNothing::class,
            [],
        );

        $node->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertUses(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $classLikeName = $desiredTagValueNode->value->value;
        $classLikeName = \ltrim($classLikeName, '\\');
        $fullyQualified = new FullyQualified($classLikeName);
        $classConst = new ClassConstFetch($fullyQualified, 'class');

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
            UsesClass::class,
            [$classConst],
        );

        // Attach the attribute to the class.
        self::$currentClassNode->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

    }

    private function convertMedium(
        Node $node,
        $phpDocInfo,
        $desiredTagValueNode,
    ): void {

        $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems(
            Medium::class,
            [],
        );

        // Attach the attribute to the class.
        self::$currentClassNode->attrGroups[] = $attributeGroup;

        // cleanup
        $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $desiredTagValueNode);

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

    private function parseTestWithData(
        string $data,
    ) {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse("<?php\n\$array = {$data};");
#        $dumper = new NodeDumper;
#        dump($dumper->dump($ast));
        dump($ast);
    }

}

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/core/tests/Drupal/Tests/Component',
#        __DIR__ . '/core',
#        __DIR__ . '/composer',
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
