<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\IncompatibleSchemaException;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaCatalog;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaInstallAction;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaPlanner;

final class SchemaCatalogAndPlannerTest extends TestCase
{
    public function testCatalogContainsOnlyTheFiveApprovedFoundationTables(): void
    {
        $catalog = new SchemaCatalog();

        self::assertSame([
            'qrkship_schema_migration',
            'qrkship_setting',
            'qrkship_secret',
            'qrkship_provider_account',
            'qrkship_audit_event',
        ], $catalog->suffixes());
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $catalog->fingerprint());
        self::assertSame($catalog->fingerprint(), (new SchemaCatalog())->fingerprint());

        foreach ($catalog->tables() as $table) {
            $sql = strtolower($table->createSql('ps_', 'utf8mb4_unicode_ci'));
            self::assertStringContainsString('engine=innodb', $sql);
            self::assertStringContainsString('charset=utf8mb4', $sql);
            self::assertStringNotContainsString(' json', $sql);
            self::assertStringNotContainsString('trigger', $sql);
            self::assertStringNotContainsString('generated', $sql);
            self::assertStringNotContainsString('foreign key', $sql);
        }
    }

    public function testPlannerCreatesOnlyWhenNoExpectedTableExists(): void
    {
        $planner = new SchemaPlanner();
        $expected = ['a', 'b', 'c'];

        self::assertSame(SchemaInstallAction::CREATE, $planner->plan($expected, [])->action());
        self::assertSame(SchemaInstallAction::ADOPT_EXISTING, $planner->plan($expected, ['c', 'a', 'b'])->action());

        $this->expectException(IncompatibleSchemaException::class);
        $planner->plan($expected, ['a']);
    }
}
