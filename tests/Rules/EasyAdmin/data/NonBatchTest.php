<?php

namespace Tourze\PHPStanSymfonyWebTest\Tests\Rules\EasyAdmin\Data;

use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

abstract class NonBatchTest extends AbstractWebTestCase
{
    public function testNormalAction(): void
    {
        $client = static::createClientWithDatabase();
        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
    }
}
