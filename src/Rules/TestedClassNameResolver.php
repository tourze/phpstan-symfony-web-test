<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyWebTest\Rules;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;

/**
 * 解析测试类所覆盖的被测类名
 *
 * 支持三种解析来源：
 * 1. PHPUnit #[CoversClass] 属性
 * 2. DocBlock @covers / @coversDefaultClass 注解
 * 3. 从测试类名推断（如 UserControllerTest → UserController）
 */
readonly class TestedClassNameResolver
{
    /**
     * 解析测试类所覆盖的被测类名列表
     *
     * @return list<string>
     */
    public function resolve(InClassNode $node, Scope $scope, string $testClassName): array
    {
        $testedClassNames = [];

        // 1. 从属性解析
        $testedClassNames = array_merge(
            $testedClassNames,
            $this->parseFromAttributes($node, $scope)
        );

        // 2. 从 DocBlock 解析
        $testedClassNames = array_merge(
            $testedClassNames,
            $this->parseFromDocBlock($node, $scope)
        );

        // 3. 如果前两种方式都没找到，从测试类名推断
        if ([] === $testedClassNames) {
            $guess = $this->guessFromTestName($testClassName);

            if (null !== $guess) {
                $testedClassNames[] = $guess;
            }
        }

        // 去重并过滤空值
        $testedClassNames = array_filter($testedClassNames, static fn (?string $name) => null !== $name);

        /** @var list<string> $unique */
        $unique = array_values(array_unique($testedClassNames));

        return $unique;
    }

    /**
     * 从 PHPUnit 属性解析被测类名
     *
     * @return list<string>
     */
    private function parseFromAttributes(InClassNode $node, Scope $scope): array
    {
        $classNode = $node->getOriginalNode();
        $testedClassNames = [];

        foreach ($classNode->attrGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attribute) {
                $attributeName = $scope->resolveName($attribute->name);

                if ('PHPUnit\Framework\Attributes\CoversClass' !== $attributeName) {
                    continue;
                }

                if ([] === $attribute->args) {
                    continue;
                }

                $possibleClassName = $this->resolveClassNameFromExpression($attribute->args[0]->value, $scope);

                if (null !== $possibleClassName) {
                    $testedClassNames[] = $possibleClassName;
                }
            }
        }

        return $testedClassNames;
    }

    /**
     * 从 DocBlock 注解解析被测类名
     *
     * @return list<string>
     */
    private function parseFromDocBlock(InClassNode $node, Scope $scope): array
    {
        $classNode = $node->getOriginalNode();
        $testedClassNames = [];

        $docComment = $classNode->getDocComment();

        if (null === $docComment) {
            return [];
        }

        $splitResult = preg_split('/\R/', $docComment->getText());
        $lines = $splitResult !== false ? $splitResult : [];

        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B*");

            if ('' === $line) {
                continue;
            }

            // 解析 @coversDefaultClass
            if (1 === preg_match('/@coversDefaultClass\s+([^\s]+)/', $line, $matches)) {
                $className = $this->normalizeDocCommentClassName($matches[1], $scope);

                if (null !== $className) {
                    $testedClassNames[] = $className;
                }

                continue;
            }

            // 解析 @covers
            if (1 === preg_match('/@covers\s+([^\s]+)/', $line, $matches)) {
                $className = $this->normalizeDocCommentClassName($matches[1], $scope);

                if (null !== $className) {
                    $testedClassNames[] = $className;
                }
            }
        }

        return $testedClassNames;
    }

    /**
     * 从测试类名推断被测类名
     *
     * 规则：移除 Test 后缀，并将 Tests/Test 命名空间替换为普通命名空间
     * 例如：App\Tests\Controller\UserControllerTest → App\Controller\UserController
     */
    private function guessFromTestName(string $testClassName): ?string
    {
        if (!str_ends_with($testClassName, 'Test')) {
            return null;
        }

        $className = substr($testClassName, 0, -4);

        return str_replace(
            ['\Tests\\', '\tests\\', '\Test\\', '\test\\'],
            '\\',
            $className
        );
    }

    /**
     * 从表达式中解析类名
     */
    private function resolveClassNameFromExpression(Expr $expression, Scope $scope): ?string
    {
        // 处理 SomeClass::class 形式
        if ($expression instanceof ClassConstFetch && $expression->name instanceof Identifier && 'class' === strtolower($expression->name->toString())) {
            $class = $expression->class;

            if ($class instanceof Name) {
                return $scope->resolveName($class);
            }
        }

        // 处理字符串形式
        if ($expression instanceof String_) {
            return $this->resolveNameFromRawString($expression->value, $scope);
        }

        return null;
    }

    /**
     * 规范化 DocBlock 中的类名
     */
    private function normalizeDocCommentClassName(string $rawClassName, Scope $scope): ?string
    {
        $className = trim($rawClassName);

        // 跳过空值或方法引用
        if ('' === $className || str_starts_with($className, '::')) {
            return null;
        }

        // 跳过 self/static/parent 引用
        if (str_starts_with($className, 'self::') || str_starts_with($className, 'static::') || str_starts_with($className, 'parent::')) {
            return null;
        }

        // 提取类名部分（去掉方法名）
        if (str_contains($className, '::')) {
            [$className] = explode('::', $className, 2);
        }

        return $this->resolveNameFromRawString($className, $scope);
    }

    /**
     * 从原始字符串解析完全限定类名
     */
    private function resolveNameFromRawString(string $rawClassName, Scope $scope): ?string
    {
        $trimmed = trim($rawClassName);

        if ('' === $trimmed) {
            return null;
        }

        $isFullyQualified = str_starts_with($trimmed, '\\');
        $normalized = ltrim($trimmed, '\\');

        if ('' === $normalized) {
            return null;
        }

        $nameNode = $isFullyQualified ? new FullyQualified($normalized) : new Name($normalized);

        return $scope->resolveName($nameNode);
    }
}
