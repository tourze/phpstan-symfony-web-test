<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyWebTest\Tests\Rules;

use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tourze\PHPStanSymfonyWebTest\Rules\ControllerTestMustExtendAbstractWebTestCaseRule;

/**
 * 测试 ControllerTestMustExtendAbstractWebTestCaseRule 规则
 *
 * @extends RuleTestCase<ControllerTestMustExtendAbstractWebTestCaseRule>
 * @internal
 */
#[CoversClass(ControllerTestMustExtendAbstractWebTestCaseRule::class)]
final class ControllerTestMustExtendAbstractWebTestCaseRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ControllerTestMustExtendAbstractWebTestCaseRule(
            $this->createMock(ReflectionProvider::class)
        );
    }

    public function testGetNodeTypeShouldReturnInClassNode(): void
    {
        $reflectionProvider = $this->createMock(ReflectionProvider::class);
        $rule = new ControllerTestMustExtendAbstractWebTestCaseRule($reflectionProvider);
        $this->assertSame(InClassNode::class, $rule->getNodeType());
    }

    /**
     * 数据提供者，测试各种场景
     */
    public static function ruleTestProvider(): array
    {
        return [
            // 正确的控制器测试类，继承自 AbstractWebTestCase
            'valid_controller_test_extending_abstract_web_test_case' => [
                'code' => '<?php
namespace App\Tests\Controller;

use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use App\Controller\UserController;

#[CoversClass(UserController::class)]
final class UserControllerTest extends AbstractWebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClientWithDatabase();
        $this->loginAsAdmin($client);

        $crawler = $client->request(\'GET\', \'/users\');
        $this->assertResponseIsSuccessful();
    }
}',
                'expectedErrors' => [],
            ],

            // 使用 @coversDefaultClass 的正确控制器测试
            'valid_controller_test_with_covers_default_class' => [
                'code' => '<?php
namespace App\Tests\Controller;

use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use App\Controller\OrderController;

/**
 * @coversDefaultClass App\Controller\OrderController
 */
final class OrderControllerTest extends AbstractWebTestCase
{
    public function testCreate(): void
    {
        $client = static::createClientWithDatabase();
        $this->loginAsUser($client);

        $crawler = $client->request(\'POST\', \'/orders/create\');
        $this->assertResponseIsSuccessful();
    }
}',
                'expectedErrors' => [],
            ],

            // 通过类名推断的控制器测试
            'valid_controller_test_by_name_inference' => [
                'code' => '<?php
namespace App\Tests\Controller;

use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

final class ProductControllerTest extends AbstractWebTestCase
{
    public function testShow(): void
    {
        $client = static::createClientWithDatabase();
        $crawler = $client->request(\'GET\', \'/products/1\');
        $this->assertResponseIsSuccessful();
    }
}',
                'expectedErrors' => [],
            ],

            // 错误的控制器测试类，没有继承 AbstractWebTestCase
            'invalid_controller_test_not_extending_abstract_web_test_case' => [
                'code' => '<?php
namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use App\Controller\UserController;

#[CoversClass(UserController::class)]
final class UserControllerTest extends TestCase
{
    public function testIndex(): void
    {
        $this->assertTrue(true);
    }
}',
                'expectedErrors' => [
                    [
                        'message' => '控制器测试类 "App\Tests\Controller\UserControllerTest" 必须继承 Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase，当前父类：PHPUnit\Framework\TestCase。',
                        'line' => 6,
                    ],
                ],
            ],

            // 非控制器测试类，应该被忽略
            'non_controller_test_should_be_ignored' => [
                'code' => '<?php
namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\UserService;

#[CoversClass(UserService::class)]
final class UserServiceTest extends TestCase
{
    public function testCreate(): void
    {
        $this->assertTrue(true);
    }
}',
                'expectedErrors' => [],
            ],

            // 抽象测试类，应该被忽略
            'abstract_test_class_should_be_ignored' => [
                'code' => '<?php
namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use App\Controller\UserController;

#[CoversClass(UserController::class)]
abstract class AbstractUserControllerTest extends TestCase
{
    abstract public function testIndex(): void;
}',
                'expectedErrors' => [],
            ],

            // 不以 Test 结尾的类，应该被忽略
            'class_not_ending_with_test_should_be_ignored' => [
                'code' => '<?php
namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use App\Controller\UserController;

#[CoversClass(UserController::class)]
final class UserControllerHelper extends TestCase
{
    public function someMethod(): void
    {
        $this->assertTrue(true);
    }
}',
                'expectedErrors' => [],
            ],

            // 非 Tests 命名空间的类，应该被忽略
            'class_not_in_tests_namespace_should_be_ignored' => [
                'code' => '<?php
namespace App\Controller;

use PHPUnit\Framework\TestCase;

#[CoversClass(self::class)]
final class UserController extends TestCase
{
    public function testSomething(): void
    {
        $this->assertTrue(true);
    }
}',
                'expectedErrors' => [],
            ],

            // 测试类继承了错误的父类
            'controller_test_extending_wrong_parent' => [
                'code' => '<?php
namespace App\Tests\Controller;

use Some\Other\BaseClass;
use App\Controller\UserController;

#[CoversClass(UserController::class)]
final class UserControllerTest extends BaseClass
{
    public function testIndex(): void
    {
        $this->assertTrue(true);
    }
}',
                'expectedErrors' => [
                    [
                        'message' => '控制器测试类 "App\Tests\Controller\UserControllerTest" 必须继承 Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase，当前父类：Some\Other\BaseClass。',
                        'line' => 7,
                    ],
                ],
            ],

            // 没有父类的测试类
            'controller_test_without_parent' => [
                'code' => '<?php
namespace App\Tests\Controller;

use App\Controller\UserController;

#[CoversClass(UserController::class)]
final class UserControllerTest
{
    public function testIndex(): void
    {
        echo "test";
    }
}',
                'expectedErrors' => [
                    [
                        'message' => '控制器测试类 "App\Tests\Controller\UserControllerTest" 必须继承 Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase，当前父类：none。',
                        'line' => 5,
                    ],
                ],
            ],

            // Test 目录下的控制器测试（另一种命名空间格式）
            'controller_test_in_test_namespace' => [
                'code' => '<?php
namespace App\Test\Controller;

use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use App\Controller\InvoiceController;

#[CoversClass(InvoiceController::class)]
final class InvoiceControllerTest extends AbstractWebTestCase
{
    public function testGenerate(): void
    {
        $client = static::createClientWithDatabase();
        $this->assertResponseIsSuccessful();
    }
}',
                'expectedErrors' => [],
            ],
        ];
    }

    /**
     * 使用数据提供者测试各种场景
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
    public function testRuleShouldProvideHelpfulErrorMessage(): void
    {
        $code = '<?php
namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use App\Controller\UserController;

#[CoversClass(UserController::class)]
final class UserControllerTest extends TestCase
{
    public function testIndex(): void
    {
        $this->assertTrue(true);
    }
}';

        $tempFile = $this->createTempFile($code);
        $errors = $this->gatherAnalyserErrors([$tempFile]);

        $targetErrors = array_filter($errors, static fn ($e) => str_contains($e->getMessage(), '必须继承'));

        foreach ($targetErrors as $error) {
            $message = $error->getMessage();

            // 检查错误消息包含关键信息
            $this->assertStringContainsString('必须继承', $message);
            $this->assertStringContainsString('AbstractWebTestCase', $message);
            $this->assertStringContainsString('createClientWithDatabase', $message);
            $this->assertStringContainsString('loginAsAdmin', $message);
            $this->assertStringContainsString('示例', $message);
        }
    }

    private function createTempFile(string $code): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test_');
        file_put_contents($tempFile, $code);

        return $tempFile;
    }
}
