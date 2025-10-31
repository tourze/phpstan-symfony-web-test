<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyWebTest\Tests\Rules\ReturnValue;

use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tourze\PHPStanSymfonyWebTest\Rules\ReturnValue\ControllerResponseTypeRule;

/**
 * 测试 ControllerResponseTypeRule 规则
 *
 * @extends RuleTestCase<ControllerResponseTypeRule>
 * @internal
 */
#[CoversClass(ControllerResponseTypeRule::class)]
final class ControllerResponseTypeRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ControllerResponseTypeRule();
    }

    public function testGetNodeTypeShouldReturnInClassMethodNode(): void
    {
        $rule = new ControllerResponseTypeRule();
        $this->assertSame(InClassMethodNode::class, $rule->getNodeType());
    }

    /**
     * 数据提供者，测试各种场景
     *
     * @return array<string, array{code: string, expectedErrors: list<array{message: string, line: int}>}>
     */
    public static function ruleTestProvider(): array
    {
        return [
            // 正确的控制器 __invoke 方法，返回 Response
            'valid_controller_invoke_with_response_return_type' => [
                'code' => '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class UserController extends AbstractController
{
    public function __invoke(): Response
    {
        return new Response(\'Hello World\');
    }
}',
                'expectedErrors' => [],
            ],

            // 正确的控制器 __invoke 方法，返回 JsonResponse
            'valid_controller_invoke_with_json_response_return_type' => [
                'code' => '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiController extends AbstractController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([\'message\' => \'Hello\']);
    }
}',
                'expectedErrors' => [],
            ],

            // 正确的控制器 __invoke 方法，返回 Response 子类
            'valid_controller_invoke_with_response_subclass' => [
                'code' => '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class CustomResponse extends Response {}

final class CustomController extends AbstractController
{
    public function __invoke(): CustomResponse
    {
        return new CustomResponse(\'Custom\');
    }
}',
                'expectedErrors' => [],
            ],

            // 错误的控制器 __invoke 方法，没有返回类型声明
            'invalid_controller_invoke_without_return_type' => [
                'code' => '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class BadController extends AbstractController
{
    public function __invoke()
    {
        return \'string response\';
    }
}',
                'expectedErrors' => [
                    [
                        'message' => '控制器 App\Controller\BadController::__invoke() 必须返回 Symfony\Component\HttpFoundation\Response 或其子类，当前推断类型为 string。',
                        'line' => 7,
                    ],
                ],
            ],

            // 错误的控制器 __invoke 方法，返回字符串
            'invalid_controller_invoke_returning_string' => [
                'code' => '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class StringController extends AbstractController
{
    public function __invoke(): string
    {
        return \'Hello World\';
    }
}',
                'expectedErrors' => [
                    [
                        'message' => '控制器 App\Controller\StringController::__invoke() 必须返回 Symfony\Component\HttpFoundation\Response 或其子类，当前推断类型为 string。',
                        'line' => 7,
                    ],
                ],
            ],

            // 错误的控制器 __invoke 方法，返回数组
            'invalid_controller_invoke_returning_array' => [
                'code' => '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class ArrayController extends AbstractController
{
    public function __invoke(): array
    {
        return [\'message\' => \'Hello\'];
    }
}',
                'expectedErrors' => [
                    [
                        'message' => '控制器 App\Controller\ArrayController::__invoke() 必须返回 Symfony\Component\HttpFoundation\Response 或其子类，当前推断类型为 array。',
                        'line' => 7,
                    ],
                ],
            ],

            // 错误的控制器 __invoke 方法，返回 void
            'invalid_controller_invoke_returning_void' => [
                'code' => '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class VoidController extends AbstractController
{
    public function __invoke(): void
    {
        // 没有返回值
    }
}',
                'expectedErrors' => [
                    [
                        'message' => '控制器 App\Controller\VoidController::__invoke() 必须返回 Symfony\Component\HttpFoundation\Response 或其子类，当前推断类型为 void。',
                        'line' => 7,
                    ],
                ],
            ],

            // 非 __invoke 方法，应该被忽略
            'non_invoke_method_should_be_ignored' => [
                'code' => '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class UserController extends AbstractController
{
    public function index(): string
    {
        return \'Hello World\';
    }

    public function create(): array
    {
        return [\'success\' => true];
    }
}',
                'expectedErrors' => [],
            ],

            // 非控制器类，应该被忽略
            'non_controller_class_should_be_ignored' => [
                'code' => '<?php
namespace App\Service;

class UserService
{
    public function __invoke(): string
    {
        return \'service result\';
    }
}',
                'expectedErrors' => [],
            ],

            // 控制器类名不以 Controller 结尾但继承 AbstractController
            'controller_without_controller_suffix_extending_abstract_controller' => [
                'code' => '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class User extends AbstractController
{
    public function __invoke(): Response
    {
        return new Response(\'Hello\');
    }
}',
                'expectedErrors' => [],
            ],

            // 控制器类名以 Controller 结尾但不继承 AbstractController
            'controller_with_suffix_not_extending_abstract_controller' => [
                'code' => '<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

final class TestController
{
    public function __invoke(): string
    {
        return \'Hello\';
    }
}',
                'expectedErrors' => [
                    [
                        'message' => '控制器 App\Controller\TestController::__invoke() 必须返回 Symfony\Component\HttpFoundation\Response 或其子类，当前推断类型为 string。',
                        'line' => 6,
                    ],
                ],
            ],

            // 控制器 __invoke 方法返回可空 Response
            'invalid_controller_invoke_returning_nullable_response' => [
                'code' => '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class NullableController extends AbstractController
{
    public function __invoke(): ?Response
    {
        return null;
    }
}',
                'expectedErrors' => [
                    [
                        'message' => '控制器 App\Controller\NullableController::__invoke() 必须返回 Symfony\Component\HttpFoundation\Response 或其子类，当前推断类型为 Symfony\Component\HttpFoundation\Response|null。',
                        'line' => 8,
                    ],
                ],
            ],

            // 控制器 __invoke 方法返回联合类型
            'invalid_controller_invoke_returning_union_type' => [
                'code' => '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class UnionController extends AbstractController
{
    public function __invoke(): Response|string
    {
        return new Response(\'Hello\');
    }
}',
                'expectedErrors' => [
                    [
                        'message' => '控制器 App\Controller\UnionController::__invoke() 必须返回 Symfony\Component\HttpFoundation\Response 或其子类，当前推断类型为 Symfony\Component\HttpFoundation\Response|string。',
                        'line' => 8,
                    ],
                ],
            ],

            // 传统 Symfony Controller（非 AbstractController）
            'legacy_symfony_controller' => [
                'code' => '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

if (class_exists(Controller::class)) {
    final class LegacyController extends Controller
    {
        public function __invoke(): Response
        {
            return new Response(\'Legacy\');
        }
    }
}',
                'expectedErrors' => [],
            ],
        ];
    }

    /**
     * 使用数据提供者测试各种场景
     *
     * @param list<array{message: string, line: int}> $expectedErrors
     */
    #[DataProvider('ruleTestProvider')]
    public function testRuleWithVariousScenarios(string $code, array $expectedErrors): void
    {
        $tempFile = $this->createTempFile($code);

        if ($expectedErrors === []) {
            $this->analyse([$tempFile], []);
        } else {
            $actualErrors = [];
            foreach ($expectedErrors as $expectedError) {
                $actualErrors[] = [$expectedError['message'], $expectedError['line']];
            }
            $this->analyse([$tempFile], $actualErrors);
        }
    }

    #[Test]
    public function testRuleShouldProvideHelpfulTips(): void
    {
        $code = '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class BadController extends AbstractController
{
    public function __invoke()
    {
        return \'string\';
    }
}';

        $tempFile = $this->createTempFile($code);
        $errors = $this->gatherAnalyserErrors([$tempFile]);

        $targetErrors = array_filter($errors, static fn ($e) => str_contains($e->getMessage(), '必须返回'));

        foreach ($targetErrors as $error) {
            $tip = $error->getTip();
            $this->assertNotNull($tip);
            $this->assertStringContainsString('返回类型声明', $tip);
            $this->assertStringContainsString('JsonResponse', $tip);
        }
    }

    #[Test]
    public function testRuleShouldUseCorrectErrorIdentifier(): void
    {
        $code = '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class BadController extends AbstractController
{
    public function __invoke(): string
    {
        return \'Hello\';
    }
}';

        $tempFile = $this->createTempFile($code);
        $errors = $this->gatherAnalyserErrors([$tempFile]);

        $targetErrors = array_filter($errors, static fn ($e) => str_contains($e->getMessage(), '必须返回'));

        foreach ($targetErrors as $error) {
            $this->assertEquals('controller.invokeResponse', $error->getIdentifier());
        }
    }

    #[Test]
    public function testRuleShouldIgnoreNonInvokeMethods(): void
    {
        $code = '<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class UserController extends AbstractController
{
    public function index(): string
    {
        return \'Hello\';
    }

    public function create(): array
    {
        return [\'success\' => true];
    }

    public function update(): void
    {
        // no return
    }
}';

        $tempFile = $this->createTempFile($code);
        $errors = $this->gatherAnalyserErrors([$tempFile]);

        // Should not generate any errors since none of these methods are __invoke
        $targetErrors = array_filter($errors, static fn ($e) => str_contains($e->getMessage(), '必须返回'));
        $this->assertCount(0, $targetErrors);
    }

    #[Test]
    public function testRuleShouldHandleComplexNamespaces(): void
    {
        $code = '<?php
namespace App\Api\v1\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class UserController extends AbstractController
{
    public function __invoke(): string
    {
        return \'Hello\';
    }
}';

        $tempFile = $this->createTempFile($code);
        $errors = $this->gatherAnalyserErrors([$tempFile]);

        $targetErrors = array_filter($errors, static fn ($e) => str_contains($e->getMessage(), '必须返回'));

        foreach ($targetErrors as $error) {
            $this->assertStringContainsString('App\Api\v1\Controller\Admin\UserController', $error->getMessage());
        }
    }

    private function createTempFile(string $code): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test_');
        file_put_contents($tempFile, $code);

        return $tempFile;
    }
}
