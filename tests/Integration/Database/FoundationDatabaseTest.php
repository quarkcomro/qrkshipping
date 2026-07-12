<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Integration\Database;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Install\FoundationInstaller;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Install\FoundationUninstaller;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Persistence\PrestaShopTransactionManager;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Persistence\TransactionOperationException;
use Qrk\Commerce\Shipping\Application\Settings\SettingValueCodec;
use Qrk\Commerce\Shipping\Application\Settings\SettingsCatalog;
use Qrk\Commerce\Shipping\Application\Settings\SettingsService;
use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;
use Qrk\Commerce\Shipping\Domain\Audit\AuditEvent;
use Qrk\Commerce\Shipping\Domain\Security\EncryptedSecret;
use Qrk\Commerce\Shipping\Domain\Security\SecretLocator;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Repository\DbAuditEventRepository;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Repository\DbSecretRepository;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Repository\DbSettingRepository;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Repository\ScopePersistenceMapper;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\IncompatibleSchemaException;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\MigrationRecorder;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\NullSchemaStepObserver;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaCatalog;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaInstallAction;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaInstallationException;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaInspector;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaManager;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaPlanner;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaStepObserver;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\TableDefinition;
use Qrk\Commerce\Shipping\Port\Persistence\AuditEventRepositoryPort;
use Qrk\Commerce\Shipping\Tests\Support\FrozenClock;
use Qrk\Commerce\Shipping\Tests\Support\PdoDatabaseConnection;
use RuntimeException;

#[Group('database')]
final class FoundationDatabaseTest extends TestCase
{
    private PDO $pdo;
    private PdoDatabaseConnection $connection;
    private SchemaCatalog $catalog;
    private ScopePersistenceMapper $scopeMapper;
    private FrozenClock $clock;
    private string $prefix;

    protected function setUp(): void
    {
        $dsn = getenv('QRK_DB_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('QRK_DB_DSN is not configured.');
        }

        $user = getenv('QRK_DB_USER');
        $password = getenv('QRK_DB_PASSWORD');

        $this->pdo = new PDO(
            $dsn,
            is_string($user) ? $user : '',
            is_string($password) ? $password : '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => true,
            ],
        );
        $this->pdo->exec(
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'",
        );

        $this->connection = new PdoDatabaseConnection($this->pdo);
        $this->catalog = new SchemaCatalog();
        $this->scopeMapper = new ScopePersistenceMapper();
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-07-11 12:00:00', new DateTimeZone('UTC')));
        $this->prefix = 'qrkt_' . bin2hex(random_bytes(4)) . '_';
    }

    protected function tearDown(): void
    {
        if (!isset($this->connection, $this->catalog, $this->prefix)) {
            return;
        }

        foreach (array_reverse($this->qrkshipNamespaceTableNames()) as $tableName) {
            $this->connection->execute(sprintf('DROP TABLE IF EXISTS `%s`', $tableName));
        }
    }

    public function testCleanInstallAndRetainedSchemaAdoptionAreConvergent(): void
    {
        $manager = $this->schemaManager();

        self::assertSame(SchemaInstallAction::CREATE, $manager->install());
        self::assertSame($this->expectedTableNames(), $this->existingTableNames());
        $manager->assertHealthy();

        self::assertSame(SchemaInstallAction::ADOPT_EXISTING, $this->schemaManager()->install());
        self::assertSame(1, $this->rowCount('qrkship_schema_migration'));
        $this->schemaManager()->assertHealthy();
    }

    public function testPartialSchemaFailsClosedWithoutCreatingMissingTables(): void
    {
        $firstTable = $this->catalog->tables()[0];
        $collation = (new SchemaInspector($this->connection, $this->prefix))->currentUtf8mb4Collation();
        $this->connection->execute($firstTable->createSql($this->prefix, $collation));

        try {
            $this->schemaManager()->install();
            self::fail('A partial schema must fail closed.');
        } catch (IncompatibleSchemaException) {
            self::assertSame([$firstTable->fullName($this->prefix)], $this->existingTableNames());
        }
    }

    public function testEveryInjectedDdlFailureRollsBackOnlyCurrentRunTables(): void
    {
        for ($failureStep = 1; $failureStep <= count($this->catalog->tables()); ++$failureStep) {
            try {
                $this->schemaManager(new class($failureStep) implements SchemaStepObserver {
                    public function __construct(
                        private readonly int $failureStep,
                    ) {
                    }

                    public function afterTableCreated(string $tableName, int $step): void
                    {
                        if ($step === $this->failureStep) {
                            throw new RuntimeException('Injected DDL finalization failure.');
                        }
                    }
                })->install();
                self::fail('Injected schema failure did not abort installation.');
            } catch (SchemaInstallationException $exception) {
                self::assertSame([], $exception->rollbackFailures());
                self::assertSame([], $this->existingTableNames());
            }
        }
    }

    public function testFinalizationFailureRollsBackFreshFoundation(): void
    {
        $auditRepository = new class implements AuditEventRepositoryPort {
            public function append(AuditEvent $event): void
            {
                throw new RuntimeException('Injected audit failure.');
            }
        };
        $installer = new FoundationInstaller($this->schemaManager(), $auditRepository, $this->clock);

        try {
            $installer->install();
            self::fail('Finalization failure did not abort installation.');
        } catch (SchemaInstallationException $exception) {
            self::assertSame([], $exception->rollbackFailures());
            self::assertSame([], $this->existingTableNames());
        }
    }

    public function testUninstallDeletesSecretsAndRetainsAllFoundationTables(): void
    {
        $this->schemaManager()->install();
        $secretRepository = new DbSecretRepository(
            $this->connection,
            $this->scopeMapper,
            $this->clock,
            $this->prefix,
        );
        $locator = new SecretLocator(1, SettingScope::shop(1, 1), 'api_token');
        $secretRepository->save($locator, new EncryptedSecret(
            1,
            'test-key',
            base64_encode(random_bytes(12)),
            base64_encode(random_bytes(32)),
            base64_encode(random_bytes(16)),
        ));

        $uninstaller = new FoundationUninstaller(
            $this->connection,
            $secretRepository,
            new DbAuditEventRepository($this->connection, $this->scopeMapper, $this->prefix),
            $this->clock,
            $this->catalog,
            $this->prefix,
        );

        self::assertSame(1, $uninstaller->removeSecretsAndKeepData());
        self::assertSame(0, $this->rowCount('qrkship_secret'));
        self::assertSame($this->expectedTableNames(), $this->existingTableNames());
        self::assertGreaterThanOrEqual(1, $this->rowCount('qrkship_audit_event'));
        self::assertSame(SchemaInstallAction::ADOPT_EXISTING, $this->schemaManager()->install());
    }

    public function testPurgeUninstallDeletesEveryTableInTheQrkshipNamespace(): void
    {
        $this->schemaManager()->install();
        $this->connection->execute(sprintf(
            'CREATE TABLE `%sqrkship_future_extension` (`id` INT NOT NULL PRIMARY KEY) '
            . 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->prefix,
        ));
        $secretRepository = new DbSecretRepository(
            $this->connection,
            $this->scopeMapper,
            $this->clock,
            $this->prefix,
        );
        $locator = new SecretLocator(1, SettingScope::shop(1, 1), 'api_token');
        $secretRepository->save($locator, new EncryptedSecret(
            1,
            'test-key',
            base64_encode(random_bytes(12)),
            base64_encode(random_bytes(32)),
            base64_encode(random_bytes(16)),
        ));

        $uninstaller = new FoundationUninstaller(
            $this->connection,
            $secretRepository,
            new DbAuditEventRepository($this->connection, $this->scopeMapper, $this->prefix),
            $this->clock,
            $this->catalog,
            $this->prefix,
        );

        self::assertSame(1, $uninstaller->purgeAllData());
        self::assertSame([], $this->qrkshipNamespaceTableNames());
        self::assertSame(SchemaInstallAction::CREATE, $this->schemaManager()->install());
        self::assertSame($this->expectedTableNames(), $this->existingTableNames());
    }

    public function testSettingsInheritanceAndAuditAreAtomicOnRealDatabase(): void
    {
        $this->schemaManager()->install();
        $auditRepository = new DbAuditEventRepository($this->connection, $this->scopeMapper, $this->prefix);
        $settings = new SettingsService(
            new SettingsCatalog(),
            new SettingValueCodec(),
            new DbSettingRepository($this->connection, $this->scopeMapper, $this->clock, $this->prefix),
            $auditRepository,
            new PrestaShopTransactionManager($this->connection),
            $this->clock,
        );
        $actor = AuditActor::system();

        $settings->set(SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL, SettingScope::all(), 'standard', $actor);
        $settings->set(
            SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL,
            SettingScope::shopGroup(7),
            'detailed',
            $actor,
        );

        $resolved = $settings->resolve(
            SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL,
            SettingScope::shop(11, 7),
        );
        self::assertSame('detailed', $resolved->value());
        self::assertTrue($resolved->sourceScope()?->equals(SettingScope::shopGroup(7)) ?? false);

        $settings->removeOverride(
            SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL,
            SettingScope::shopGroup(7),
            $actor,
        );
        self::assertSame(
            'standard',
            $settings->resolve(
                SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL,
                SettingScope::shop(11, 7),
            )->value(),
        );
        self::assertSame(3, $this->rowCount('qrkship_audit_event'));
    }

    public function testAuditFailureRollsBackTheSettingMutation(): void
    {
        $this->schemaManager()->install();
        $settings = new SettingsService(
            new SettingsCatalog(),
            new SettingValueCodec(),
            new DbSettingRepository($this->connection, $this->scopeMapper, $this->clock, $this->prefix),
            new class implements AuditEventRepositoryPort {
                public function append(AuditEvent $event): void
                {
                    throw new RuntimeException('Injected audit failure.');
                }
            },
            new PrestaShopTransactionManager($this->connection),
            $this->clock,
        );

        try {
            $settings->set(
                SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL,
                SettingScope::all(),
                'detailed',
                AuditActor::system(),
            );
            self::fail('Audit failure did not abort the setting mutation.');
        } catch (TransactionOperationException $exception) {
            self::assertFalse($exception->rollbackFailed());
            self::assertSame(0, $this->rowCount('qrkship_setting'));
            self::assertSame(0, $this->rowCount('qrkship_audit_event'));
        }
    }

    public function testLifecyclePolicyMutationRollsBackAtomicallyWhenSecondAuditFails(): void
    {
        $this->schemaManager()->install();
        $auditRepository = new class implements AuditEventRepositoryPort {
            private int $calls = 0;

            public function append(AuditEvent $event): void
            {
                ++$this->calls;
                if ($this->calls === 2) {
                    throw new RuntimeException('Injected second audit failure.');
                }
            }
        };
        $settings = new SettingsService(
            new SettingsCatalog(),
            new SettingValueCodec(),
            new DbSettingRepository($this->connection, $this->scopeMapper, $this->clock, $this->prefix),
            $auditRepository,
            new PrestaShopTransactionManager($this->connection),
            $this->clock,
        );

        try {
            $settings->setMany(
                [
                    SettingsCatalog::LIFECYCLE_PURGE_ON_UNINSTALL => true,
                    SettingsCatalog::LIFECYCLE_RESET_TO_DEFAULTS => true,
                ],
                SettingScope::all(),
                AuditActor::system(),
            );
            self::fail('Second audit failure did not abort the lifecycle-policy mutation.');
        } catch (TransactionOperationException $exception) {
            self::assertFalse($exception->rollbackFailed());
            self::assertSame(0, $this->rowCount('qrkship_setting'));
            self::assertSame(0, $this->rowCount('qrkship_audit_event'));
        }
    }

    public function testStrictSqlModeAndNonstandardPrefixAreActive(): void
    {
        $modeRow = $this->connection->fetchOne('SELECT @@SESSION.sql_mode AS `mode`');
        self::assertNotNull($modeRow);
        $mode = (string) ($modeRow['mode'] ?? '');
        self::assertStringContainsString('STRICT_TRANS_TABLES', $mode);
        self::assertStringStartsWith('qrkt_', $this->prefix);

        $this->schemaManager()->install();
        self::assertSame($this->expectedTableNames(), $this->existingTableNames());
    }

    private function schemaManager(?SchemaStepObserver $observer = null): SchemaManager
    {
        $inspector = new SchemaInspector($this->connection, $this->prefix);

        return new SchemaManager(
            $this->connection,
            $this->catalog,
            $inspector,
            new SchemaPlanner(),
            new MigrationRecorder($this->connection, $this->prefix),
            $this->clock,
            $this->prefix,
            $observer ?? new NullSchemaStepObserver(),
        );
    }

    /**
     * @return list<string>
     */
    private function expectedTableNames(): array
    {
        $names = array_map(
            fn (TableDefinition $table): string => $table->fullName($this->prefix),
            $this->catalog->tables(),
        );
        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    private function existingTableNames(): array
    {
        $quotedNames = implode(', ', array_map(
            fn (string $name): string => $this->connection->quote($name),
            $this->expectedTableNames(),
        ));
        $rows = $this->connection->fetchAll(
            'SELECT `TABLE_NAME` FROM `INFORMATION_SCHEMA`.`TABLES` '
            . 'WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` IN (' . $quotedNames . ') '
            . 'ORDER BY `TABLE_NAME` ASC',
        );

        return array_map(
            static fn (array $row): string => (string) ($row['TABLE_NAME'] ?? ''),
            $rows,
        );
    }

    /**
     * @return list<string>
     */
    private function qrkshipNamespaceTableNames(): array
    {
        $namespacePrefix = $this->prefix . 'qrkship_';
        $rows = $this->connection->fetchAll(sprintf(
            'SELECT `TABLE_NAME` FROM `INFORMATION_SCHEMA`.`TABLES` '
            . 'WHERE `TABLE_SCHEMA` = DATABASE() AND LEFT(`TABLE_NAME`, %d) = %s '
            . 'ORDER BY `TABLE_NAME` ASC',
            strlen($namespacePrefix),
            $this->connection->quote($namespacePrefix),
        ));

        return array_map(
            static fn (array $row): string => (string) ($row['TABLE_NAME'] ?? ''),
            $rows,
        );
    }

    private function rowCount(string $suffix): int
    {
        $row = $this->connection->fetchOne(sprintf(
            'SELECT COUNT(*) AS `count` FROM `%s%s`',
            $this->prefix,
            $suffix,
        ));

        return (int) ($row['count'] ?? 0);
    }
}
