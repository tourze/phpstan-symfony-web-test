<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyWebTest\Tests\Rules;

use PhpParser\Node\Stmt\Class_;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tourze\PHPStanSymfonyWebTest\Rules\RequireRunInSeparateProcessRule;

/**
 * 测试 RequireRunInSeparateProcessRule 规则
 *
 * @extends RuleTestCase<RequireRunInSeparateProcessRule>
 * @internal
 */
#[CoversClass(RequireRunInSeparateProcessRule::class)]
final class RequireRunInSeparateProcessRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new RequireRunInSeparateProcessRule(
            $this->createMock(ReflectionProvider::class)
        );
    }

    public function testGetNodeTypeShouldReturnClass(): void
    {
        $reflectionProvider = $this->createMock(ReflectionProvider::class);
        $rule = new RequireRunInSeparateProcessRule($reflectionProvider);
        $this->assertSame(Class_::class, $rule->getNodeType());
    }

    /**
     * 数据提供者，测试各种场景
     *
     * @return array<string, array{code: string, expectedErrors: list<array{message: string, line: int}>}>
     */
    public static function ruleTestProvider(): array
    {
        return [
            // 正确的测试类，有 RunTestsInSeparateProcesses 注解（完整类名）
            'valid_class_with_full_attribute_name' => [
                'code' => '<?php
namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

#[RunTestsInSeparateProcesses]
final class UserControllerTest extends AbstractWebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClientWithDatabase();
        $this->assertResponseIsSuccessful();
    }
}',
                'expectedErrors' => [],
            ],

            // 正确的测试类，有 RunTestsInSeparateProcesses 注解（短名称）
            'valid_class_with_short_attribute_name' => [
                'code' => '<?php
namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

#[RunTestsInSeparateProcesses]
final class OrderControllerTest extends AbstractWebTestCase
{
    public function testCreate(): void
    {
        $client = static::createClientWithDatabase();
        $this->assertResponseIsSuccessful();
    }
}',
                'expectedErrors' => [],
            ],

            // 正确的测试类，有多个属性但包含 RunTestsInSeparateProcesses
            'valid_class_with_multiple_attributes' => [
                'code' => '<?php
namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use App\Controller\ProductController;

#[CoversClass(ProductController::class)]
#[Group(\'integration\')]
#[RunTestsInSeparateProcesses]
final class ProductControllerTest extends AbstractWebTestCase
{
    public function testShow(): void
    {
        $client = static::createClientWithDatabase();
        $this->assertResponseIsSuccessful();
    }
}',
                'expectedErrors' => [],
            ],

            // 错误的测试类，缺少 RunTestsInSeparateProcesses 注解
            'invalid_class_missing_attribute' => [
                'code' => '<?php
namespace App\Tests\Controller;

use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

final class UserControllerTest extends AbstractWebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClientWithDatabase();
        $this->assertResponseIsSuccessful();
    }
}',
                'expectedErrors' => [
                    [
                        'message' => '测试类 App\Tests\Controller\UserControllerTest 必须使用 #[RunTestsInSeparateProcesses] 注解来确保测试隔离',
                        'line' => 5,
                    ],
                ],
            ],

            // 非 AbstractWebTestCase 子类，应该被忽略
            'non_web_test_case_should_be_ignored' => [
                'code' => '<?php
namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;

final class UserServiceTest extends TestCase
{
    public function testCreate(): void
    {
        $this->assertTrue(true);
    }
}',
                'expectedErrors' => [],
            ],

            // AbstractWebTestCase 基类本身，应该被忽略
            'abstract_web_test_case_base_class_should_be_ignored' => [
                'code' => '<?php
namespace Tourze\PHPUnitSymfonyWebTest;

use PHPUnit\Framework\TestCase;

abstract class AbstractWebTestCase extends TestCase
{
    public static function createClientWithDatabase(): void
    {
        // implementation
    }
}',
                'expectedErrors' => [],
            ],

            // 抽象测试类继承自 AbstractWebTestCase，应该被忽略（因为没有具体类）
            'abstract_test_class_extending_web_test_case' => [
                'code' => '<?php
namespace App\Tests\Controller;

use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

abstract class AbstractControllerTest extends AbstractWebTestCase
{
    abstract public function testSomething(): void;
}',
                'expectedErrors' => [],
            ],

            // 测试类有其他属性但没有 RunTestsInSeparateProcesses
            'class_with_other_attributes_but_missing_required_one' => [
                'code' => '<?php
namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use App\Controller\InvoiceController;

#[CoversClass(InvoiceController::class)]
#[Group(\'slow\')]
final class InvoiceControllerTest extends AbstractWebTestCase
{
    public function testGenerate(): void
    {
        $client = static::createClientWithDatabase();
        $this->assertResponseIsSuccessful();
    }
}',
                'expectedErrors' => [
                    [
                        'message' => '测试类 App\Tests\Controller\InvoiceControllerTest 必须使用 #[RunTestsInSeparateProcesses] 注解来确保测试隔离',
                        'line' => 10,
                    ],
                ],
            ],

            // 类没有名称（匿名类），应该被忽略
            'anonymous_class_should_be_ignored' => [
                'code' => '<?php
namespace App\Tests\Controller;

use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

$test = new class extends AbstractWebTestCase {
    public function testMethod(): void {
        $this->assertTrue(true);
    }
};',
                'expectedErrors' => [],
            ],

            // 使用完整命名空间的属性
            'valid_class_with_full_namespace_attribute' => [
                'code' => '<?php
namespace App\Tests\Controller;

use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

#[PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class ReportControllerTest extends AbstractWebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClientWithDatabase();
        $this->assertResponseIsSuccessful();
    }
}',
                'expectedErrors' => [],
            ],

            // 错误的属性名称拼写
            'invalid_class_with_misspelled_attribute' => [
                'code' => '<?php
namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\RunInSeparateProcess; // 错误的名称
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

#[RunInSeparateProcess]
final class SettingsControllerTest extends AbstractWebTestCase
{
    public function testUpdate(): void
    {
        $client = static::createClientWithDatabase();
        $this->assertResponseIsSuccessful();
    }
}',
                'expectedErrors' => [
                    [
                        'message' => '测试类 App\Tests\Controller\SettingsControllerTest 必须使用 #[RunTestsInSeparateProcesses] 注解来确保测试隔离',
                        'line' => 9,
                    ],
                ],
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
    public function testRuleShouldProvideHelpfulTip(): void
    {
        $code = '<?php
namespace App\Tests\Controller;

use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

final class UserControllerTest extends AbstractWebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClientWithDatabase();
        $this->assertResponseIsSuccessful();
    }
}';

        $tempFile = $this->createTempFile($code);
        $errors = $this->gatherAnalyserErrors([$tempFile]);

        $targetErrors = array_filter($errors, static fn ($e) => str_contains($e->getMessage(), '必须使用'));

        foreach ($targetErrors as $error) {
            $tip = $error->getTip();
            $this->assertNotNull($tip);
            $this->assertStringContainsString('use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;', $tip);
            $this->assertStringContainsString('#[RunTestsInSeparateProcesses]', $tip);
        }
    }

    #[Test]
    public function testRuleShouldIgnoreNonWebTestCaseClasses(): void
    {
        $code = '<?php
namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;

final class UserServiceTest extends TestCase
{
    public function testCreate(): void
    {
        $this->assertTrue(true);
    }
}';

        $tempFile = $this->createTempFile($code);
        $errors = $this->gatherAnalyserErrors([$tempFile]);

        // Should not generate any errors since UserServiceTest is not a AbstractWebTestCase
        $targetErrors = array_filter($errors, static fn ($e) => str_contains($e->getMessage(), '必须使用'));
        $this->assertCount(0, $targetErrors);
    }

    #[Test]
    public function testRuleShouldHandleComplexNamespaces(): void
    {
        $code = '<?php
namespace App\Tests\Integration\Controller\Api\v1;

use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

final class ApiControllerTest extends AbstractWebTestCase
{
    public function testApi(): void
    {
        $client = static::createClientWithDatabase();
        $this->assertResponseIsSuccessful();
    }
}';

        $tempFile = $this->createTempFile($code);
        $errors = $this->gatherAnalyserErrors([$tempFile]);

        $targetErrors = array_filter($errors, static fn ($e) => str_contains($e->getMessage(), '必须使用'));

        foreach ($targetErrors as $error) {
            $this->assertStringContainsString('App\Tests\Integration\Controller\Api\v1\ApiControllerTest', $error->getMessage());
        }
    }

    private function createTempFile(string $code): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_test_');
        file_put_contents($tempFile, $code);

        return $tempFile;
    }
}
