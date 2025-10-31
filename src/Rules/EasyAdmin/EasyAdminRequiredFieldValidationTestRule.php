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
class EasyAdminRequiredFieldValidationTestRule implements Rule
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

        // 分析 Controller 中的必填字段
        if ($this->hasRequiredFields($controllerClass) && !$this->hasValidationTest($classReflection)) {
            return [
                RuleErrorBuilder::message(
                    'Controller有必填字段但缺少验证测试'
                )
                    ->tip(
                        '添加 testValidationErrors() 方法，提交空表单并验证错误信息：' . PHP_EOL .
                        '$crawler = $client->submit($form);' . PHP_EOL .
                        '$this->assertResponseStatusCodeSame(422);' . PHP_EOL .
                        '$this->assertStringContainsString("should not be blank", $crawler->filter(".invalid-feedback")->text());'
                    )
                    ->identifier('phpstan.symfonyWebTest.easyAdminRequiredFieldValidationTest')
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

    private function hasRequiredFields(string $controllerClass): bool
    {
        try {
            $reflection = new \ReflectionClass($controllerClass);
            if (!$reflection->hasMethod('configureFields')) {
                return false;
            }

            $method = $reflection->getMethod('configureFields');

            // 通过反射获取方法体来分析字段配置
            $filename = $method->getFileName();
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();

            if ($filename && $startLine && $endLine) {
                $lines = file($filename);
                $methodBody = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

                // 查找 setRequired(true) 的调用
                if (preg_match('/->setRequired\s*\(\s*true\s*\)/', $methodBody)) {
                    return true;
                }

                // 也检查直接在 new 时传递的 required 参数
                if (preg_match('/Field::new\s*\([^)]+\)\s*->.*required.*true/', $methodBody)) {
                    return true;
                }
            }
        } catch (\ReflectionException $e) {
            // 忽略反射错误
        }

        return false;
    }

    private function hasValidationTest(ClassReflection $classReflection): bool
    {
        try {
            $nativeReflection = $classReflection->getNativeReflection();
            $candidates = $this->filterValidationCandidates($nativeReflection->getMethods());

            foreach ($candidates as $method) {
                if ($this->methodContainsRequiredAssertions($method)) {
                    return true;
                }
            }
        } catch (\ReflectionException $e) {
            // 忽略反射错误
        }

        return false;
    }

    /**
     * 筛选出可能是验证测试的方法
     *
     * @param array<\ReflectionMethod> $methods
     * @return array<\ReflectionMethod>
     */
    private function filterValidationCandidates(array $methods): array
    {
        $candidates = [];

        foreach ($methods as $method) {
            $methodName = $method->getName();

            // 检查方法名是否暗示验证测试
            if (str_starts_with($methodName, 'test')
                && (false !== stripos($methodName, 'validation')
                 || false !== stripos($methodName, 'error')
                 || false !== stripos($methodName, 'required')
                 || false !== stripos($methodName, 'blank')
                 || false !== stripos($methodName, 'empty'))) {
                $candidates[] = $method;
            }
        }

        return $candidates;
    }

    /**
     * 检查方法体是否包含必填字段验证的断言
     */
    private function methodContainsRequiredAssertions(\ReflectionMethod $method): bool
    {
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        if (false === $filename || false === $startLine || false === $endLine) {
            return false;
        }

        $lines = file($filename);
        if (!is_array($lines)) {
            return false;
        }

        $methodBody = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        // 检查是否有验证相关的断言
        $hasStatusCodeAssertion = 1 === preg_match('/assertResponseStatusCodeSame\s*\(\s*422\s*\)/', $methodBody);
        $hasInvalidFeedback = 1 === preg_match('/invalid-feedback/', $methodBody);
        $hasShouldNotBeBlank = 1 === preg_match('/should not be blank/', $methodBody);

        return $hasStatusCodeAssertion || $hasInvalidFeedback || $hasShouldNotBeBlank;
    }
}
