<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyWebTest\Rules\EasyAdmin;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * 禁止在同一个方法上同时使用 EasyAdmin 的 AdminAction 与 Symfony 的 Route 注解。
 *
 * @implements Rule<ClassMethod>
 */
final class ForbidAdminActionWithRouteTogetherRule implements Rule
{
    private const ADMIN_ACTION_NAMES = [
        'EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction',
        'AdminAction',
    ];

    private const ROUTE_NAMES = [
        'Symfony\Component\Routing\Attribute\Route',
        'Route',
    ];

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @return array<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod) {
            return [];
        }

        // 只检查有 AttributeGroup 的方法
        if ($node->attrGroups === []) {
            return [];
        }

        $hasAdminAction = $this->containsAttribute($node->attrGroups, self::ADMIN_ACTION_NAMES);
        if (!$hasAdminAction) {
            return [];
        }

        $hasRoute = $this->containsAttribute($node->attrGroups, self::ROUTE_NAMES);

        return $hasRoute ? [$this->buildError($node)] : [];
    }

    /**
     * 检查属性组中是否包含指定名称的属性
     *
     * @param array<AttributeGroup> $attrGroups
     * @param array<string> $targetNames
     */
    private function containsAttribute(array $attrGroups, array $targetNames): bool
    {
        foreach ($attrGroups as $group) {
            if (!$group instanceof AttributeGroup) {
                continue;
            }

            foreach ($group->attrs as $attr) {
                if (!$attr instanceof Attribute) {
                    continue;
                }

                $name = $this->resolveAttributeName($attr);
                if (null === $name) {
                    continue;
                }

                if (in_array($name, $targetNames, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 解析属性名称
     */
    private function resolveAttributeName(Attribute $attr): ?string
    {
        // $attr->name 可能是 Name|FullyQualified|Identifier，统一转字符串
        try {
            return (string) $attr->name;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 构建错误信息
     */
    private function buildError(ClassMethod $node): RuleError
    {
        return RuleErrorBuilder::message('同一方法禁止同时使用 AdminAction 与 Route 注解，请择一使用。')
            ->line($node->getStartLine())
            ->build();
    }
}
