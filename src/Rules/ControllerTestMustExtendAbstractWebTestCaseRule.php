<?php

namespace Tourze\PHPStanSymfonyWebTest\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @implements Rule<InClassNode>
 */
readonly class ControllerTestMustExtendAbstractWebTestCaseRule implements Rule
{
    private TestedClassNameResolver $testedClassNameResolver;

    public function __construct(
        private ReflectionProvider $reflectionProvider,
        ?TestedClassNameResolver $testedClassNameResolver = null
    ) {
        $this->testedClassNameResolver = $testedClassNameResolver ?? new TestedClassNameResolver();
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();
        $className = $classReflection->getName();

        // 跳过非测试类
        if (!$this->isTestClass($className)) {
            return [];
        }

        // 跳过抽象类
        if ($classReflection->isAbstract()) {
            return [];
        }

        // 检查是否是 Controller 测试类
        if (!$this->isControllerTestClass($node, $scope, $classReflection)) {
            return [];
        }

        // 检查是否继承自 AbstractWebTestCase
        if (!$classReflection->isSubclassOf(AbstractWebTestCase::class)) {
            $parentClass = $classReflection->getParentClass();
            $currentParent = $parentClass !== null ? $parentClass->getName() : 'none';

            $message = sprintf(
                <<<'MSG'
                    控制器测试类 "%s" 必须继承 %s，当前父类：%s。

                    为什么要继承 AbstractWebTestCase：
                    - 提供 createClientWithDatabase() 等 HTTP 客户端能力
                    - 负责测试用数据库的初始化与清理
                    - 内置 loginAsAdmin()/loginAsUser() 等鉴权辅助方法
                    - 支持表单交互与响应断言
                    - 集成 EasyAdmin 的专用测试辅助工具

                    示例：
                    ```php
                    use %s;
                    use Symfony\Component\HttpKernel\KernelInterface;

                    final class %s extends AbstractWebTestCase
                    {
                        public function testIndex(): void
                        {
                            $client = static::createClientWithDatabase();
                            $this->loginAsAdmin($client);

                            $crawler = $client->request('GET', '/my-route');
                            $this->assertResponseIsSuccessful();
                        }
                    }
                    ```

                    参考文档：https://symfony.com/doc/current/testing.html#application-tests
                    MSG,
                $className,
                AbstractWebTestCase::class,
                $currentParent,
                AbstractWebTestCase::class,
                $this->getSimpleClassName($className)
            );

            return [
                RuleErrorBuilder::message($message)->build(),
            ];
        }

        return [];
    }

    private function isTestClass(string $className): bool
    {
        // 检查类名是否以 Test 结尾
        if (!str_ends_with($className, 'Test')) {
            return false;
        }

        // 检查是否在 tests 目录下
        if (!str_contains($className, '\Tests\\') && !str_contains($className, '\Test\\')) {
            return false;
        }

        return true;
    }

    private function isControllerTestClass(InClassNode $node, Scope $scope, ClassReflection $testClassReflection): bool
    {
        $testedClassNames = $this->testedClassNameResolver->resolve($node, $scope, $testClassReflection->getName());

        foreach ($testedClassNames as $testedClassName) {
            // 优先通过反射检查实际的类结构
            if ($this->reflectionProvider->hasClass($testedClassName)) {
                $testedClassReflection = $this->reflectionProvider->getClass($testedClassName);

                if ($this->isControllerClass($testedClassReflection)) {
                    return true;
                }
            } else {
                // 如果类不存在（如测试环境中的临时类），通过类名推断
                if ($this->looksLikeControllerByName($testedClassName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 通过类名判断是否看起来像控制器
     * 用于处理 ReflectionProvider 无法找到类的场景（如测试环境）
     */
    private function looksLikeControllerByName(string $className): bool
    {
        return str_ends_with($className, 'Controller');
    }


    private function isControllerClass(ClassReflection $classReflection): bool
    {
        if ($classReflection->isInterface() || $classReflection->isAnonymous()) {
            return false;
        }

        // 检查类名后缀
        if ($this->hasControllerNameSuffix($classReflection)) {
            return true;
        }

        // 检查继承关系
        if ($this->extendsKnownControllerBase($classReflection)) {
            return true;
        }

        // 检查 Trait
        if ($this->usesControllerTrait($classReflection)) {
            return true;
        }

        return false;
    }

    /**
     * 检查类名是否以 Controller 结尾
     */
    private function hasControllerNameSuffix(ClassReflection $classReflection): bool
    {
        return str_ends_with($classReflection->getName(), 'Controller');
    }

    /**
     * 检查是否继承自已知的控制器基类
     */
    private function extendsKnownControllerBase(ClassReflection $classReflection): bool
    {
        $knownControllerBases = [
            'Symfony\Bundle\FrameworkBundle\Controller\AbstractController',
            'Symfony\Bundle\FrameworkBundle\Controller\Controller',
            'EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController',
            'EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController',
            'Symfony\Component\HttpKernel\Controller\ControllerInterface',
        ];

        foreach ($knownControllerBases as $baseClass) {
            if ($classReflection->isSubclassOf($baseClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查是否使用了 ControllerTrait
     */
    private function usesControllerTrait(ClassReflection $classReflection): bool
    {
        return $this->usesTrait($classReflection, 'Symfony\Bundle\FrameworkBundle\Controller\ControllerTrait');
    }

    private function usesTrait(ClassReflection $classReflection, string $traitName): bool
    {
        try {
            $nativeReflection = $classReflection->getNativeReflection();
        } catch (\Throwable) {
            return false;
        }

        return in_array($traitName, $nativeReflection->getTraitNames(), true);
    }

    private function getSimpleClassName(string $fullClassName): string
    {
        $parts = explode('\\', $fullClassName);

        return end($parts);
    }

}
