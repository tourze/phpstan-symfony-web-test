<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyWebTest\Rules\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClassMethod>
 */
class AdminActionRouteParametersRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod) {
            return [];
        }

        $errors = [];

        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                if (!$this->isAdminActionAttribute($attribute)) {
                    continue;
                }

                $params = $this->collectAdminActionParameters($attribute);

                if (!$params['hasRouteName']) {
                    $errors[] = $this->buildMissingParameterError('routeName', $attribute);
                }

                if (!$params['hasRoutePath']) {
                    $errors[] = $this->buildMissingParameterError('routePath', $attribute);
                }
            }
        }

        return $errors;
    }

    /**
     * 检查属性是否是 AdminAction
     */
    private function isAdminActionAttribute(Node\Attribute $attribute): bool
    {
        return AdminAction::class === $attribute->name->toString();
    }

    /**
     * 收集 AdminAction 属性的参数信息
     *
     * @return array{hasRouteName: bool, hasRoutePath: bool}
     */
    private function collectAdminActionParameters(Node\Attribute $attribute): array
    {
        $hasRouteName = false;
        $hasRoutePath = false;

        foreach ($attribute->args as $arg) {
            if (null === $arg->name) {
                continue;
            }

            $paramName = $arg->name->toString();
            if ('routeName' === $paramName) {
                $hasRouteName = true;
            }

            if ('routePath' === $paramName) {
                $hasRoutePath = true;
            }
        }

        return [
            'hasRouteName' => $hasRouteName,
            'hasRoutePath' => $hasRoutePath,
        ];
    }

    /**
     * 构建缺失参数的错误信息
     */
    private function buildMissingParameterError(string $parameterName, Node\Attribute $attribute): IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            sprintf('The #[AdminAction] attribute must have both "routeName" and "routePath" parameters.')
        )
            ->line($attribute->getStartLine())
            ->identifier('easyadmin.admin.action.route.parameters')
            ->build();
    }
}
