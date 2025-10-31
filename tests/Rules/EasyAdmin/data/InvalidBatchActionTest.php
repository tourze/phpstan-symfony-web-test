<?php

namespace Tourze\PHPStanSymfonyWebTest\Tests\Rules\EasyAdmin\Data;

use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

abstract class InvalidBatchActionTest extends AbstractWebTestCase
{
    public function testBatchDelete(): void
    {
        $client = static::createClientWithDatabase();
        $client->request('POST', '/admin');

        $this->assertResponseIsSuccessful();
    }
}
