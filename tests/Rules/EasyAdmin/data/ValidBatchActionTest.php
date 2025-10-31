<?php

namespace Tourze\PHPStanSymfonyWebTest\Tests\Rules\EasyAdmin\Data;

use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

abstract class ValidBatchActionTest extends AbstractWebTestCase
{
    public function testBatchDelete(): void
    {
        $client = static::createClientWithDatabase();
        $client->request('POST', '/admin', [
            'ea' => [
                'batchActionName' => 'batchDelete',
                'batchActionEntityIds' => [1, 2, 3],
                'crudControllerFqcn' => 'App\Controller\Admin\UserCrudController',
            ],
        ]);

        $this->assertResponseIsSuccessful();
    }
}
