<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Install;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Install\FoundationUninstaller;
use Qrk\Commerce\Shipping\Application\Lifecycle\LifecycleOperation;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaCatalog;
use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;
use Qrk\Commerce\Shipping\Tests\Support\FrozenClock;
use Qrk\Commerce\Shipping\Tests\Support\InMemoryAuditEventRepository;
use Qrk\Commerce\Shipping\Tests\Support\InMemorySecretRepository;
use RuntimeException;

final class FoundationUninstallerTest extends TestCase
{
    public function testPurgeDropsEveryTableInTheQrkshipNamespace(): void
    {
        $connection = new class implements DatabaseConnectionPort {
            /** @var list<string> */
            public array $executed = [];
            private int $metadataReads = 0;

            public function execute(string $sql): void
            {
                $this->executed[] = $sql;
            }

            public function fetchOne(string $sql): ?array
            {
                return null;
            }

            public function fetchAll(string $sql): array
            {
                ++$this->metadataReads;
                if ($this->metadataReads === 1) {
                    return [
                        ['TABLE_NAME' => 'test_qrkship_future_extension'],
                        ['TABLE_NAME' => 'test_qrkship_setting'],
                    ];
                }

                return [];
            }

            public function quote(string $value): string
            {
                return "'" . str_replace("'", "''", $value) . "'";
            }

            public function lastInsertId(): int
            {
                return 0;
            }

            public function affectedRows(): int
            {
                return 0;
            }
        };
        $catalog = new SchemaCatalog();
        $uninstaller = new FoundationUninstaller(
            $connection,
            new InMemorySecretRepository(),
            new InMemoryAuditEventRepository(),
            new FrozenClock(new DateTimeImmutable('2026-07-12T12:00:00+00:00')),
            $catalog,
            'test_',
        );

        self::assertSame(0, $uninstaller->purgeAllData());

        $expectedNames = ['test_qrkship_future_extension'];
        foreach ($catalog->tables() as $table) {
            $expectedNames[] = $table->fullName('test_');
        }
        $expectedNames = array_values(array_unique($expectedNames));
        sort($expectedNames);
        $expectedIdentifiers = array_map(
            static fn (string $tableName): string => '`' . $tableName . '`',
            array_reverse($expectedNames),
        );
        self::assertSame(
            ['DROP TABLE IF EXISTS ' . implode(', ', $expectedIdentifiers)],
            $connection->executed,
        );
    }

    public function testPurgeFailsClosedWhenAnyQrkshipTableStillExists(): void
    {
        $connection = new class implements DatabaseConnectionPort {
            private int $metadataReads = 0;

            public function execute(string $sql): void
            {
            }

            public function fetchOne(string $sql): ?array
            {
                return null;
            }

            public function fetchAll(string $sql): array
            {
                ++$this->metadataReads;

                return $this->metadataReads === 1
                    ? []
                    : [['TABLE_NAME' => 'test_qrkship_future_extension']];
            }

            public function quote(string $value): string
            {
                return "'" . str_replace("'", "''", $value) . "'";
            }

            public function lastInsertId(): int
            {
                return 0;
            }

            public function affectedRows(): int
            {
                return 0;
            }
        };
        $uninstaller = new FoundationUninstaller(
            $connection,
            new InMemorySecretRepository(),
            new InMemoryAuditEventRepository(),
            new FrozenClock(new DateTimeImmutable('2026-07-12T12:00:00+00:00')),
            new SchemaCatalog(),
            'test_',
        );

        $this->expectException(RuntimeException::class);
        $uninstaller->purgeAllData();
    }

    public function testPurgeRejectsUnsafeNamespaceIdentifiers(): void
    {
        $connection = new class implements DatabaseConnectionPort {
            public function execute(string $sql): void
            {
            }

            public function fetchOne(string $sql): ?array
            {
                return null;
            }

            public function fetchAll(string $sql): array
            {
                return [['TABLE_NAME' => 'test_qrkship_unsafe-name']];
            }

            public function quote(string $value): string
            {
                return "'" . str_replace("'", "''", $value) . "'";
            }

            public function lastInsertId(): int
            {
                return 0;
            }

            public function affectedRows(): int
            {
                return 0;
            }
        };
        $uninstaller = new FoundationUninstaller(
            $connection,
            new InMemorySecretRepository(),
            new InMemoryAuditEventRepository(),
            new FrozenClock(new DateTimeImmutable('2026-07-12T12:00:00+00:00')),
            new SchemaCatalog(),
            'test_',
        );

        $this->expectException(RuntimeException::class);
        $uninstaller->purgeAllData();
    }

    public function testRetainedResetUsesResetSpecificAuditEvent(): void
    {
        $connection = new class implements DatabaseConnectionPort {
            public function execute(string $sql): void
            {
            }

            public function fetchOne(string $sql): ?array
            {
                return ['TABLE_NAME' => 'test_qrkship_secret'];
            }

            public function fetchAll(string $sql): array
            {
                return [];
            }

            public function quote(string $value): string
            {
                return "'" . str_replace("'", "''", $value) . "'";
            }

            public function lastInsertId(): int
            {
                return 0;
            }

            public function affectedRows(): int
            {
                return 0;
            }
        };
        $audit = new InMemoryAuditEventRepository();
        $uninstaller = new FoundationUninstaller(
            $connection,
            new InMemorySecretRepository(),
            $audit,
            new FrozenClock(new DateTimeImmutable('2026-07-12T12:00:00+00:00')),
            new SchemaCatalog(),
            'test_',
        );

        $uninstaller->uninstall(false, LifecycleOperation::RESET);

        self::assertCount(1, $audit->events);
        self::assertSame('module.reset_secrets_removed', $audit->events[0]->eventCode());
        self::assertSame('reset', $audit->events[0]->metadata()['lifecycle_operation'] ?? null);
    }
}
