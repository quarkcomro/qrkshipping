<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Persistence;

use Db;
use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Persistence\PrestaShopDatabaseConnection;

final class PrestaShopDatabaseConnectionTest extends TestCase
{
    protected function tearDown(): void
    {
        Db::deleteTestingInstance();
    }

    public function testAuthoritativeReadsBypassPrestaShopSqlCache(): void
    {
        $db = new Db();
        $db->executeSResult = [['value' => 'fresh']];
        Db::setInstanceForTesting($db);

        $rows = (new PrestaShopDatabaseConnection())->fetchAll('SELECT fresh metadata');

        self::assertSame([['value' => 'fresh']], $rows);
        self::assertSame(
            [['sql' => 'SELECT fresh metadata', 'array' => true, 'use_cache' => false]],
            $db->executeSCalls,
        );
    }
}
