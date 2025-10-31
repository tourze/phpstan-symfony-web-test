<?php

namespace Tourze\PHPStanSymfonyWebTest\Rules\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @implements Rule<InClassNode>
 */
class EasyAdminCustomActionTestCoverageRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        // 仅检查继承 AbstractWebTestCase 的测试类
        if (!$classReflection->isSubclassOf(AbstractWebTestCase::class)) {
            return [];
        }

        // 检查是否是 EasyAdmin 的测试类
        $controllerClass = $this->getTestedControllerClass($classReflection);
        if (null === $controllerClass || !is_subclass_of($controllerClass, AbstractCrudController::class)) {
            return [];
        }

        // 分析 Controller 中的自定义 Action
        $customActions = $this->extractCustomActions($controllerClass);

        // 如果没有自定义 Action，不需要测试
        if ($customActions === []) {
            return [];
        }

        // 检查缺失的测试
        $missingTests = [];
        foreach ($customActions as $action) {
            if (!$this->hasTestForAction($classReflection, $action)) {
                $missingTests[] = $action;
            }
        }

        if ($missingTests !== []) {
            return [
                RuleErrorBuilder::message(
                    sprintf(
                        '缺少对自定义动作的测试：%s',
                        implode(', ', $missingTests)
                    )
                )
                    ->tip(
                        sprintf(
                            '为动作 %s 添加测试方法 test%s()，使用 $client->request("GET", "/admin/your-entity/{id}/%s") 触发该动作',
                            $missingTests[0],
                            ucfirst($missingTests[0]),
                            $this->camelToKebab($missingTests[0])
                        )
                    )
                    ->identifier('phpstan.symfonyWebTest.easyAdminCustomActionTestCoverage')
                    ->build(),
            ];
        }

        return [];
    }

    private function getTestedControllerClass(ClassReflection $classReflection): ?string
    {
        // 从 CoversClass 属性获取
        try {
            $nativeReflection = $classReflection->getNativeReflection();
            if (method_exists($nativeReflection, 'getAttributes')) {
                $attributes = $nativeReflection->getAttributes(CoversClass::class);
                if (count($attributes) > 0) {
                    $arguments = $attributes[0]->getArguments();
                    if (isset($arguments[0])) {
                        return ltrim($arguments[0], '\\');
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // 忽略反射错误
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractCustomActions(string $controllerClass): array
    {
        $actions = [];

        try {
            $reflection = new \ReflectionClass($controllerClass);

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                // 跳过继承的方法和魔术方法
                if ($method->getDeclaringClass()->getName() !== $controllerClass) {
                    continue;
                }

                if (str_starts_with($method->getName(), '__')) {
                    continue;
                }

                // 检查是否有 AdminAction 属性
                if (method_exists($method, 'getAttributes')) {
                    $attributes = $method->getAttributes(AdminAction::class);
                    if (count($attributes) > 0) {
                        $actions[] = $method->getName();
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // 忽略反射错误
        }

        return $actions;
    }

    private function hasTestForAction(ClassReflection $classReflection, string $action): bool
    {
        try {
            $nativeReflection = $classReflection->getNativeReflection();

            // 检查多种可能的测试方法命名
            $possibleTestNames = [
                'test' . ucfirst($action),
                'test' . ucfirst($action) . 'Action',
                'testCustomAction' . ucfirst($action),
            ];

            foreach ($nativeReflection->getMethods() as $method) {
                if (in_array($method->getName(), $possibleTestNames, true)) {
                    return true;
                }

                // 也检查方法名中是否包含该 action 名称
                if (str_starts_with($method->getName(), 'test')
                    && false !== stripos($method->getName(), $action)) {
                    return true;
                }
            }
        } catch (\ReflectionException $e) {
            // 忽略反射错误
        }

        return false;
    }

    private function camelToKebab(string $string): string
    {
        return strtolower(preg_replace('/[A-Z]/', '-$0', lcfirst($string)));
    }
}
