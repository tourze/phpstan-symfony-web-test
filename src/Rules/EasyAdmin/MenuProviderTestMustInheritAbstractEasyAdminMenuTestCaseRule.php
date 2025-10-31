<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyWebTest\Rules\EasyAdmin;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\PHPUnitBase\TestCaseHelper;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;

/**
 * 如果一个测试用例的测试目标实现了 \Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface ，那么一定要直接继承 \SymfonyTestingFramework\Test\AbstractEasyAdminMenuTestCase。
 *
 * @implements Rule<InClassNode>
 */
class MenuProviderTestMustInheritAbstractEasyAdminMenuTestCaseRule implements Rule
{
    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return [];
        }

        // 1. 检查是否是测试类
        if (!TestCaseHelper::isTestClass($classReflection->getName())) {
            return [];
        }

        // 2. 获取CoversClass注解
        $coversClass = TestCaseHelper::extractCoverClass($classReflection->getNativeReflection());
        if (null === $coversClass) {
            return [];
        }

        if (!$this->reflectionProvider->hasClass($coversClass)) {
            return [];
        }
        $coveredClassReflection = $this->reflectionProvider->getClass($coversClass);

        // 3. 检查被覆盖的类是否实现了 MenuProviderInterface
        $menuProviderInterfaceType = new ObjectType(MenuProviderInterface::class);
        if (!$menuProviderInterfaceType->isSuperTypeOf(new ObjectType($coveredClassReflection->getName()))->yes()) {
            return [];
        }

        // 4. 检查测试类是否直接继承 AbstractEasyAdminMenuTestCase
        if (!$classReflection->isSubclassOf(AbstractEasyAdminMenuTestCase::class)) {
            return [
                RuleErrorBuilder::message(sprintf(
                    '测试类 %s 测试的是 MenuProviderInterface 实现 %s，但没有继承 %s。',
                    $classReflection->getName(),
                    $coversClass,
                    AbstractEasyAdminMenuTestCase::class
                ))
                    ->identifier('easyAdmin.menuProviderTest.mustInheritAbstractEasyAdminMenuTestCase')
                    ->tip('MenuProviderInterface 实现的测试必须继承 ' . AbstractEasyAdminMenuTestCase::class . ' 以使用预设的测试环境和辅助方法。')
                    ->build(),
            ];
        }

        return [];
    }
}
