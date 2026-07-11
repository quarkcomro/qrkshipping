<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Integration\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Install\RuntimeRequirementFailure;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Install\RuntimeRequirementsChecker;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\MigrationRecorder;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\NullSchemaStepObserver;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaCatalog;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaInstallAction;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaInspector;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaManager;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaPlanner;
use Qrk\Commerce\Shipping\Tests\Support\FrozenClock;
use Qrk\Commerce\Shipping\Tests\Support\PdoDatabaseConnection;

#[Group('database')]
final class Utf8mb3DatabaseDefaultTest extends TestCase
{
    public function testLegacyDatabaseDefaultDoesNotBlockUtf8mb4Foundation(): void
    {
        $dsn = getenv('QRK_DB_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('QRK_DB_DSN is not configured.');
        }

        $pdo = new PDO(
            $dsn,
            (string) (getenv('QRK_DB_USER') ?: ''),
            (string) (getenv('QRK_DB_PASSWORD') ?: ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => true,
            ],
        );
        $pdo->exec(
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'",
        );

        $databaseStatement = $pdo->query('SELECT DATABASE()');
        self::assertNotFalse($databaseStatement);
        $databaseName = (string) $databaseStatement->fetchColumn();
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_]+$/D', $databaseName);

        $defaultsStatement = $pdo->query(
            'SELECT @@character_set_database AS `charset`, @@collation_database AS `collation`',
        );
        self::assertNotFalse($defaultsStatement);
        $defaults = $defaultsStatement->fetch();
        self::assertIsArray($defaults);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_]+$/D', (string) $defaults['charset']);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_]+$/D', (string) $defaults['collation']);

        $connection = new PdoDatabaseConnection($pdo);
        $catalog = new SchemaCatalog();
        $prefix = 'qrku_' . bin2hex(random_bytes(4)) . '_';
        $clock = new FrozenClock(new DateTimeImmutable('2026-07-11 12:00:00', new DateTimeZone('UTC')));
        $manager = new SchemaManager(
            $connection,
            $catalog,
            new SchemaInspector($connection, $prefix),
            new SchemaPlanner(),
            new MigrationRecorder($connection, $prefix),
            $clock,
            $prefix,
            new NullSchemaStepObserver(),
        );

        try {
            $pdo->exec(sprintf(
                'ALTER DATABASE `%s` CHARACTER SET utf8 COLLATE utf8_general_ci',
                $databaseName,
            ));

            if (!defined('_PS_VERSION_')) {
                define('_PS_VERSION_', '9.1.4');
            }

            $failureCodes = array_map(
                static fn (RuntimeRequirementFailure $failure): string => $failure->code(),
                (new RuntimeRequirementsChecker($connection))->check(),
            );
            self::assertSame([], $failureCodes);

            self::assertSame(SchemaInstallAction::CREATE, $manager->install());
            $manager->assertHealthy();

            foreach ($catalog->tables() as $table) {
                $row = $connection->fetchOne(sprintf(
                    'SELECT `TABLE_COLLATION` FROM `INFORMATION_SCHEMA`.`TABLES` '
                    . 'WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = %s',
                    $connection->quote($table->fullName($prefix)),
                ));
                self::assertIsArray($row);
                self::assertStringStartsWith(
                    'utf8mb4_',
                    strtolower((string) ($row['TABLE_COLLATION'] ?? '')),
                );
            }
        } finally {
            foreach (array_reverse($catalog->tables()) as $table) {
                $connection->execute(sprintf('DROP TABLE IF EXISTS `%s`', $table->fullName($prefix)));
            }
            $pdo->exec(sprintf(
                'ALTER DATABASE `%s` CHARACTER SET %s COLLATE %s',
                $databaseName,
                (string) $defaults['charset'],
                (string) $defaults['collation'],
            ));
        }
    }
}
