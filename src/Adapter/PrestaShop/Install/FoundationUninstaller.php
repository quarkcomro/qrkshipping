<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Install;

use InvalidArgumentException;
use Qrk\Commerce\Shipping\Application\Lifecycle\LifecycleOperation;
use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;
use Qrk\Commerce\Shipping\Domain\Audit\AuditEvent;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaCatalog;
use Qrk\Commerce\Shipping\Port\Clock\ClockPort;
use Qrk\Commerce\Shipping\Port\Persistence\AuditEventRepositoryPort;
use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;
use Qrk\Commerce\Shipping\Port\Persistence\SecretRepositoryPort;
use RuntimeException;
use Throwable;

final class FoundationUninstaller
{
    public function __construct(
        private readonly DatabaseConnectionPort $connection,
        private readonly SecretRepositoryPort $secrets,
        private readonly AuditEventRepositoryPort $auditEvents,
        private readonly ClockPort $clock,
        private readonly SchemaCatalog $schemaCatalog,
        private readonly string $databasePrefix,
    ) {
        if (preg_match('/^[A-Za-z0-9_]*$/D', $databasePrefix) !== 1) {
            throw new InvalidArgumentException('Database prefix is invalid.');
        }
    }

    public function uninstall(
        bool $purgeAllData,
        LifecycleOperation $operation = LifecycleOperation::UNINSTALL,
    ): int {
        $removedSecrets = $this->removeSecrets();

        if ($purgeAllData) {
            $this->dropAllFoundationTables();

            return $removedSecrets;
        }

        $this->appendRetentionAudit($removedSecrets, $operation);

        return $removedSecrets;
    }

    public function removeSecretsAndKeepData(): int
    {
        return $this->uninstall(false);
    }

    public function purgeAllData(): int
    {
        return $this->uninstall(true);
    }

    private function removeSecrets(): int
    {
        if (!$this->tableExists($this->databasePrefix . 'qrkship_secret')) {
            return 0;
        }

        return $this->secrets->deleteAll();
    }

    private function appendRetentionAudit(int $removedSecrets, LifecycleOperation $operation): void
    {
        if (!$this->tableExists($this->databasePrefix . 'qrkship_audit_event')) {
            return;
        }

        try {
            $this->auditEvents->append(new AuditEvent(
                $operation === LifecycleOperation::RESET
                    ? 'module.reset_secrets_removed'
                    : 'module.uninstalled_secrets_removed',
                'info',
                SettingScope::all(),
                AuditActor::system(),
                'module',
                'qrkshipping',
                bin2hex(random_bytes(16)),
                [
                    'removed_secret_rows' => $removedSecrets,
                    'non_secret_data_retained' => true,
                    'lifecycle_operation' => $operation->value,
                ],
                $this->clock->now(),
            ));
        } catch (Throwable) {
            // Secret deletion is mandatory. A failed best-effort audit must not restore or expose secrets.
        }
    }

    private function dropAllFoundationTables(): void
    {
        try {
            $tableNames = $this->discoverFoundationTableNames();
            foreach ($this->schemaCatalog->tables() as $table) {
                $tableNames[] = $table->fullName($this->databasePrefix);
            }
            $tableNames = array_values(array_unique($tableNames));
            sort($tableNames);

            $tableIdentifiers = array_map(
                static fn (string $tableName): string => sprintf('`%s`', $tableName),
                array_reverse($tableNames),
            );
            $this->connection->execute('DROP TABLE IF EXISTS ' . implode(', ', $tableIdentifiers));

            $remainingTables = $this->discoverFoundationTableNames();
            if ($remainingTables !== []) {
                throw new RuntimeException(sprintf(
                    'QRK Shipping tables still exist after the destructive operation: %s.',
                    implode(', ', $remainingTables),
                ));
            }
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'The QRK Shipping foundation tables could not be removed completely.',
                previous: $exception,
            );
        }
    }

    /**
     * @return list<string>
     */
    private function discoverFoundationTableNames(): array
    {
        $namespacePrefix = $this->databasePrefix . 'qrkship_';
        $rows = $this->connection->fetchAll(sprintf(
            'SELECT `TABLE_NAME` FROM `INFORMATION_SCHEMA`.`TABLES` '
            . 'WHERE `TABLE_SCHEMA` = DATABASE() AND LEFT(`TABLE_NAME`, %d) = %s '
            . 'ORDER BY `TABLE_NAME` ASC',
            strlen($namespacePrefix),
            $this->connection->quote($namespacePrefix),
        ));

        $tableNames = [];
        $pattern = '/^' . preg_quote($namespacePrefix, '/') . '[A-Za-z0-9_]+$/D';
        foreach ($rows as $row) {
            $tableName = (string) ($row['TABLE_NAME'] ?? '');
            if (preg_match($pattern, $tableName) !== 1) {
                throw new RuntimeException('An unsafe QRK Shipping table identifier was returned by the database.');
            }
            $tableNames[] = $tableName;
        }

        return array_values(array_unique($tableNames));
    }

    private function tableExists(string $tableName): bool
    {
        return $this->connection->fetchOne(sprintf(
            'SELECT `TABLE_NAME` FROM `INFORMATION_SCHEMA`.`TABLES` '
            . 'WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = %s',
            $this->connection->quote($tableName),
        )) !== null;
    }
}
