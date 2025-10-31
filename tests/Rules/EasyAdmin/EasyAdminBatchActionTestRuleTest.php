<?php

declare(strict_types=1);

namespace Tourze\PHPStanSymfonyWebTest\Tests\Rules\EasyAdmin;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tourze\PHPStanSymfonyWebTest\Rules\EasyAdmin\EasyAdminBatchActionTestRule;

/**
 * 测试 EasyAdminBatchActionTestRule 规则
 *
 * @extends RuleTestCase<EasyAdminBatchActionTestRule>
 *
 * @internal
 */
#[CoversClass(EasyAdminBatchActionTestRule::class)]
final class EasyAdminBatchActionTestRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new EasyAdminBatchActionTestRule(
            $this->createReflectionProvider()
        );
    }

    #[Test]
    public function testValidBatchActionTest(): void
    {
        $this->analyse([__DIR__ . '/data/ValidBatchActionTest.php'], []);
    }

    #[Test]
    public function testInvalidBatchActionTest(): void
    {
        $this->analyse([__DIR__ . '/data/InvalidBatchActionTest.php'], [
            [
                '批量操作测试 "testBatchDelete" 需要使用正确的HTTP请求格式',
                9,
                '使用以下格式发送批量操作请求：' . PHP_EOL .
                    '$client->request("POST", "/admin", [' . PHP_EOL .
                    '    "ea" => [' . PHP_EOL .
                    '        "batchActionName" => "batchDelete",' . PHP_EOL .
                    '        "batchActionEntityIds" => [$id1, $id2],' . PHP_EOL .
                    '        "crudControllerFqcn" => YourController::class' . PHP_EOL .
                    '    ]' . PHP_EOL .
                    ']);',
            ],
        ]);
    }

    #[Test]
    public function testNonBatchMethodShouldPass(): void
    {
        $this->analyse([__DIR__ . '/data/NonBatchTest.php'], []);
    }
}
