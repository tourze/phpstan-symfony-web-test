<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyWebTest\Rules\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * 检查 $this->addFlash() 调用时第一个参数只允许特定类型
 * 允许的类型：primary, secondary, success, danger, warning, info, light, dark
 * 禁止的类型：error
 *
 * @implements Rule<MethodCall>
 */
class EasyAdminAddFlashTypeRule implements Rule
{
    private const ALLOWED_FLASH_TYPES = [
        'primary',
        'secondary',
        'success',
        'danger',
        'warning',
        'info',
        'light',
        'dark',
    ];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     *
     * @return array<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall) {
            return [];
        }

        if (!$this->isFlashCheckCandidate($node, $scope)) {
            return [];
        }

        $error = $this->validateFlashType($node);

        return null !== $error ? [$error] : [];
    }

    /**
     * 检查节点是否是需要检查的 addFlash 调用候选
     */
    private function isFlashCheckCandidate(MethodCall $node, Scope $scope): bool
    {
        // 检查是否调用了 addFlash 方法
        if (!$node->name instanceof Identifier || 'addFlash' !== $node->name->name) {
            return false;
        }

        // 检查调用对象是否是 $this
        if (!($node->var instanceof Node\Expr\Variable && 'this' === $node->var->name)) {
            return false;
        }

        // 检查是否在控制器类中
        if (!$scope->isInClass()) {
            return false;
        }

        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return false;
        }

        return $this->isControllerClass($classReflection);
    }

    /**
     * 检查类是否是 Symfony 控制器或 EasyAdmin CRUD 控制器的子类
     */
    private function isControllerClass(\PHPStan\Reflection\ClassReflection $classReflection): bool
    {
        if ($this->reflectionProvider->hasClass(AbstractController::class)) {
            $abstractController = $this->reflectionProvider->getClass(AbstractController::class);
            if ($classReflection->isSubclassOfClass($abstractController)) {
                return true;
            }
        }

        if ($this->reflectionProvider->hasClass(AbstractCrudController::class)) {
            $abstractCrudController = $this->reflectionProvider->getClass(AbstractCrudController::class);
            if ($classReflection->isSubclassOfClass($abstractCrudController)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 验证 flash 类型，如果无效则返回错误
     */
    private function validateFlashType(MethodCall $node): ?RuleError
    {
        // 检查是否至少有一个参数
        if (0 === count($node->args)) {
            return null;
        }

        $firstArg = $node->args[0];
        if (!$firstArg instanceof Node\Arg) {
            return null;
        }

        // 检查第一个参数是否是字符串字面量
        if (!$firstArg->value instanceof String_) {
            // 如果不是字符串字面量，我们无法在静态分析时确定值
            return null;
        }

        $flashType = $firstArg->value->value;

        // 检查是否使用了禁止的类型
        if ('error' === $flashType) {
            return RuleErrorBuilder::message(
                '在 EasyAdmin 中禁止使用 "error" 作为闪存消息类型。请使用 "danger" 代替。'
            )
                ->line($node->getStartLine())
                ->build();
        }

        // 检查是否使用了允许的类型
        if (!$this->isAllowedFlashType($flashType)) {
            return RuleErrorBuilder::message(sprintf(
                '无效的闪存消息类型 "%s"。允许的类型有：%s',
                $flashType,
                implode(', ', self::ALLOWED_FLASH_TYPES)
            ))
                ->line($node->getStartLine())
                ->build();
        }

        return null;
    }

    /**
     * 检查 flash 类型是否在允许列表中
     */
    private function isAllowedFlashType(string $type): bool
    {
        return in_array($type, self::ALLOWED_FLASH_TYPES, true);
    }
}
