<?php

namespace Tourze\PHPStanSymfonyWebTest\Rules\EasyAdmin;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @implements Rule<ClassMethod>
 */
class EasyAdminBatchActionTestRule implements Rule
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return [];
        }

        // 仅对继承 AbstractWebTestCase 的类生效
        if (!$this->reflectionProvider->hasClass(AbstractWebTestCase::class)) {
            return [];
        }

        $baseClassReflection = $this->reflectionProvider->getClass(AbstractWebTestCase::class);
        if (!$classReflection->isSubclassOfClass($baseClassReflection)) {
            return [];
        }

        // 检测方法名是否暗示批量操作
        $methodName = $node->name->toString();
        if (1 !== preg_match('/^test(Batch|Bulk|Mass)/', $methodName)) {
            return [];
        }

        // 跳过抽象方法
        if ($node->isAbstract()) {
            return [];
        }

        // 检查是否使用了正确的 HTTP 请求格式
        if (!$this->hasProperBatchRequest($node)) {
            return [
                RuleErrorBuilder::message(
                    sprintf(
                        '批量操作测试 "%s" 需要使用正确的HTTP请求格式',
                        $methodName
                    )
                )
                    ->tip(
                        '使用以下格式发送批量操作请求：' . PHP_EOL .
                        '$client->request("POST", "/admin", [' . PHP_EOL .
                        '    "ea" => [' . PHP_EOL .
                        '        "batchActionName" => "batchDelete",' . PHP_EOL .
                        '        "batchActionEntityIds" => [$id1, $id2],' . PHP_EOL .
                        '        "crudControllerFqcn" => YourController::class' . PHP_EOL .
                        '    ]' . PHP_EOL .
                        ']);'
                    )
                    ->identifier('phpstan.symfonyWebTest.easyAdminBatchActionTest')
                    ->build(),
            ];
        }

        return [];
    }

    private function hasProperBatchRequest(ClassMethod $method): bool
    {
        if (null === $method->stmts) {
            return false;
        }

        $nodeFinder = new NodeFinder();
        $methodCalls = $nodeFinder->findInstanceOf($method->stmts, MethodCall::class);

        foreach ($methodCalls as $call) {
            if ($this->isRequestCall($call) && $this->hasBatchActionParameters($call)) {
                return true;
            }
        }

        return false;
    }

    private function isRequestCall(MethodCall $call): bool
    {
        return $call->name instanceof Identifier && 'request' === $call->name->toString();
    }

    private function hasBatchActionParameters(MethodCall $call): bool
    {
        $args = $call->getArgs();
        if (count($args) < 3) {
            return false;
        }

        $payload = $this->extractArrayArg($args[2]);
        if (null === $payload) {
            return false;
        }

        $eaItem = $this->findArrayItemByStringKey($payload, 'ea');

        return $eaItem instanceof ArrayItem
            && $eaItem->value instanceof Array_
            && $this->hasRequiredBatchKeys($eaItem->value);
    }

    private function extractArrayArg(Arg $arg): ?Array_
    {
        return $arg->value instanceof Array_ ? $arg->value : null;
    }

    private function findArrayItemByStringKey(Array_ $array, string $key): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if ($item instanceof ArrayItem
                && $item->key instanceof String_
                && $key === $item->key->value) {
                return $item;
            }
        }

        return null;
    }

    private function hasRequiredBatchKeys(Array_ $array): bool
    {
        $requiredKeys = ['batchActionName', 'batchActionEntityIds'];
        $foundKeys = [];

        foreach ($array->items as $item) {
            if ($item instanceof ArrayItem
                && $item->key instanceof Node\Scalar\String_) {
                $foundKeys[] = $item->key->value;
            }
        }

        foreach ($requiredKeys as $key) {
            if (!in_array($key, $foundKeys, true)) {
                return false;
            }
        }

        return true;
    }
}
