<?php

namespace Tourze\PHPStanSymfonyWebTest\Rules\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassNode>
 */
class RequireAdminCrudAttributeRule implements Rule
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        // 仅检查 AbstractCrudController 的子类
        if (!$this->reflectionProvider->hasClass(AbstractCrudController::class)) {
            return [];
        }

        $baseClassReflection = $this->reflectionProvider->getClass(AbstractCrudController::class);
        if (!$classReflection->isSubclassOfClass($baseClassReflection)) {
            return [];
        }

        // 跳过测试类
        if (str_ends_with($classReflection->getName(), 'Test')) {
            return [];
        }

        // 跳过抽象类
        if ($classReflection->isAbstract()) {
            return [];
        }

        // 检查是否有 AdminCrud 属性
        if (!$this->hasAdminCrudAttribute($classReflection)) {
            return [
                RuleErrorBuilder::message(
                    sprintf(
                        'CRUD控制器 %s 必须使用 #[AdminCrud] 注解声明路由',
                        $classReflection->getName()
                    )
                )
                    ->tip('在类上添加 #[AdminCrud(routePath: "/your-path", routeName: "your_route_name")] 注解')
                    ->identifier('phpstan.symfonyWebTest.requireAdminCrudAttribute')
                    ->build(),
            ];
        }

        return [];
    }

    private function hasAdminCrudAttribute(ClassReflection $classReflection): bool
    {
        try {
            $nativeReflection = $classReflection->getNativeReflection();

            // 检查 PHP 8 属性
            if (method_exists($nativeReflection, 'getAttributes')) {
                foreach ($nativeReflection->getAttributes() as $attribute) {
                    if (AdminCrud::class === $attribute->getName()) {
                        return true;
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // 忽略反射错误
        }

        return false;
    }
}
