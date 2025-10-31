<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyWebTest\Rules\ReturnValue;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VerbosityLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * @implements Rule<InClassMethodNode>
 */
class ControllerResponseTypeRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof InClassMethodNode);

        $methodReflection = $node->getMethodReflection();

        if ('__invoke' !== $methodReflection->getName()) {
            return [];
        }

        $classReflection = $methodReflection->getDeclaringClass();

        if (!$this->isController($classReflection)) {
            return [];
        }

        $variants = $methodReflection->getVariants();
        if (\method_exists(ParametersAcceptorSelector::class, 'selectSingle')) {
            $returnType = ParametersAcceptorSelector::selectSingle($variants)->getReturnType();
        } else {
            $returnType = $variants[0]->getReturnType();
        }

        $responseType = new ObjectType(Response::class);
        if ($responseType->isSuperTypeOf($returnType)->yes()) {
            return [];
        }

        $originalNode = $node->getOriginalNode();
        $declaredReturn = null;
        if (null !== $originalNode->returnType) {
            $declaredReturn = match (true) {
                $originalNode->returnType instanceof Name => $originalNode->returnType->toString(),
                $originalNode->returnType instanceof Identifier => $originalNode->returnType->toString(),
                default => null,
            };
        }
        $tips = [];

        if (null === $declaredReturn) {
            $tips[] = '在方法签名中添加返回类型声明，例如 ": Response"。';
        }

        $tips[] = '确保返回 Symfony\Component\HttpFoundation\Response 或其子类（如 JsonResponse）。';

        $errorBuilder = RuleErrorBuilder::message(\sprintf(
            '控制器 %s::__invoke() 必须返回 %s 或其子类，当前推断类型为 %s。',
            $classReflection->getName(),
            Response::class,
            $returnType->describe(VerbosityLevel::value())
        ))
            ->identifier('controller.invokeResponse')
            ->line($originalNode->getStartLine())
        ;

        if ([] !== $tips) {
            $errorBuilder->tip(\implode(' ', $tips));
        }

        return [$errorBuilder->build()];
    }

    private function isController(ClassReflection $classReflection): bool
    {
        if ($classReflection->isSubclassOf(AbstractController::class)) {
            return true;
        }

        $legacyController = 'Symfony\Bundle\FrameworkBundle\Controller\Controller';
        if (\class_exists($legacyController) && $classReflection->isSubclassOf($legacyController)) {
            return true;
        }

        return \str_ends_with($classReflection->getName(), 'Controller');
    }
}
