<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Schema;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\MigrationRecorder;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaCatalog;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaInstallationException;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaInspector;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaManager;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaPlanner;
use Qrk\Commerce\Shipping\Tests\Support\FailureInjectingDatabaseConnection;
use Qrk\Commerce\Shipping\Tests\Support\FrozenClock;
use Qrk\Commerce\Shipping\Tests\Support\ThrowingSchemaStepObserver;

final class SchemaRollbackTest extends TestCase
{
    #[DataProvider('failureSteps')]
    public function testEveryInjectedDdlFailureRollsBackOnlyCurrentRunTables(int $failureStep): void
    {
        $connection = new FailureInjectingDatabaseConnection();
        $catalog = new SchemaCatalog();
        $prefix = 'custom_';
        $manager = new SchemaManager(
            $connection,
            $catalog,
            new SchemaInspector($connection, $prefix),
            new SchemaPlanner(),
            new MigrationRecorder($connection, $prefix),
            new FrozenClock(new DateTimeImmutable('2026-07-11T12:00:00+00:00', new DateTimeZone('UTC'))),
            $prefix,
            new ThrowingSchemaStepObserver($failureStep),
        );

        try {
            $manager->install();
            self::fail('Expected the injected schema failure.');
        } catch (SchemaInstallationException $exception) {
            self::assertSame([], $exception->rollbackFailures());
        }

        self::assertSame([], $connection->tableNames());
        $drops = array_values(array_filter(
            $connection->executed,
            static fn (string $sql): bool => str_starts_with($sql, 'DROP TABLE IF EXISTS'),
        ));
        self::assertCount($failureStep, $drops);
    }

    /** @return iterable<string, array{int}> */
    public static function failureSteps(): iterable
    {
        for ($step = 1; $step <= 5; ++$step) {
            yield 'after table ' . $step => [$step];
        }
    }
}
