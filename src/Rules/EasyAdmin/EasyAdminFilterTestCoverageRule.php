<?php

namespace Tourze\PHPStanSymfonyWebTest\Rules\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @implements Rule<InClassNode>
 */
class EasyAdminFilterTestCoverageRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        // 仅检查继承 AbstractEasyAdminControllerTestCase 的测试类
        if (!$classReflection->isSubclassOf(AbstractEasyAdminControllerTestCase::class)) {
            return [];
        }

        // 检查是否是 EasyAdmin 的测试类
        $controllerClass = $this->getTestedControllerClass($classReflection);
        if (null === $controllerClass || !is_subclass_of($controllerClass, AbstractCrudController::class)) {
            return [];
        }

        // 分析 Controller 中配置的过滤器
        $configuredFilters = $this->analyzeConfigureFilters($controllerClass);

        // 如果没有配置过滤器，不需要测试
        if ($configuredFilters === []) {
            return [];
        }

        // 检查是否有测试搜索功能的方法
        if (!$this->hasSearchTest($classReflection)) {
            return [
                RuleErrorBuilder::message(
                    sprintf(
                        '控制器配置了 %d 个过滤器（%s），但缺少搜索功能测试',
                        count($configuredFilters),
                        implode(', ', array_slice($configuredFilters, 0, 3)) . (count($configuredFilters) > 3 ? '...' : '')
                    )
                )
                    ->tip('添加 testSearchAndFilter() 方法，使用 $client->request("GET", "/admin/your-entity", ["filters" => ["field" => "value"]) 测试过滤功能')
                    ->identifier('phpstan.symfonyWebTest.easyAdminFilterTestCoverage')
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
    private function analyzeConfigureFilters(string $controllerClass): array
    {
        $filters = [];

        try {
            $reflection = new \ReflectionClass($controllerClass);
            if (!$reflection->hasMethod('configureFilters')) {
                return [];
            }

            $method = $reflection->getMethod('configureFilters');

            // 通过反射获取方法体来分析过滤器配置
            // 这里简化处理，在实际实现中可能需要更复杂的AST分析
            $filename = $method->getFileName();
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();

            if ($filename && $startLine && $endLine) {
                $lines = file($filename);
                $methodBody = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

                // 使用正则表达式查找过滤器配置
                if (preg_match_all('/->add\s*\(\s*([A-Za-z]+Filter)::new\s*\(\s*[\'"]([^\'"]+)[\'"]/', $methodBody, $matches)) {
                    foreach ($matches[1] as $i => $filterType) {
                        $filters[] = $filterType . ':' . $matches[2][$i];
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // 忽略反射错误
        }

        return $filters;
    }

    private function hasSearchTest(ClassReflection $classReflection): bool
    {
        try {
            $nativeReflection = $classReflection->getNativeReflection();
            foreach ($nativeReflection->getMethods() as $method) {
                $methodName = $method->getName();

                // 检查方法名是否暗示搜索或过滤测试
                if (str_starts_with($methodName, 'test')
                    && (false !== stripos($methodName, 'search')
                     || false !== stripos($methodName, 'filter')
                     || false !== stripos($methodName, 'query'))) {
                    return true;
                }
            }
        } catch (\ReflectionException $e) {
            // 忽略反射错误
        }

        return false;
    }
}
