<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema;

use DateTimeImmutable;
use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;
use Qrk\Commerce\Shipping\ModuleMetadata;

final class MigrationRecorder
{
    public function __construct(
        private readonly DatabaseConnectionPort $connection,
        private readonly string $databasePrefix,
    ) {
        if (preg_match('/^[A-Za-z0-9_]*$/D', $databasePrefix) !== 1) {
            throw new \InvalidArgumentException('Database prefix is invalid.');
        }
    }

    public function ensureCurrent(SchemaCatalog $catalog, DateTimeImmutable $appliedAt): void
    {
        $row = $this->current();
        if ($row !== null) {
            $this->assertRecordMatches($row, $catalog);

            return;
        }

        $table = $this->databasePrefix . 'qrkship_schema_migration';
        $this->connection->execute(sprintf(
            'INSERT INTO `%s` '
            . '(`migration_version`, `migration_checksum`, `schema_fingerprint`, `status`, `error_code`, `applied_at`) '
            . 'VALUES (%s, %s, %s, %s, NULL, %s)',
            $table,
            $this->connection->quote(ModuleMetadata::SCHEMA_VERSION),
            $this->connection->quote($catalog->migrationChecksum()),
            $this->connection->quote($catalog->fingerprint()),
            $this->connection->quote('applied'),
            $this->connection->quote($appliedAt->format('Y-m-d H:i:s')),
        ));
    }

    public function assertCurrent(SchemaCatalog $catalog): void
    {
        $row = $this->current();
        if ($row === null) {
            throw new IncompatibleSchemaException(
                'The current foundation migration is missing from the migration journal.',
            );
        }

        $this->assertRecordMatches($row, $catalog);
    }

    /**
     * @return array{version: string, checksum: string, fingerprint: string, status: string, applied_at: string}|null
     */
    public function current(): ?array
    {
        $table = $this->databasePrefix . 'qrkship_schema_migration';
        $row = $this->connection->fetchOne(sprintf(
            'SELECT `migration_version`, `migration_checksum`, `schema_fingerprint`, `status`, `applied_at` '
            . 'FROM `%s` WHERE `migration_version` = %s',
            $table,
            $this->connection->quote(ModuleMetadata::SCHEMA_VERSION),
        ));

        if ($row === null) {
            return null;
        }

        return [
            'version' => (string) ($row['migration_version'] ?? ''),
            'checksum' => (string) ($row['migration_checksum'] ?? ''),
            'fingerprint' => (string) ($row['schema_fingerprint'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'applied_at' => (string) ($row['applied_at'] ?? ''),
        ];
    }

    /**
     * @param array{version: string, checksum: string, fingerprint: string, status: string, applied_at: string} $row
     */
    private function assertRecordMatches(array $row, SchemaCatalog $catalog): void
    {
        $appliedAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $row['applied_at']);
        $dateErrors = DateTimeImmutable::getLastErrors();
        $validAppliedAt = $appliedAt !== false
            && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
            && $appliedAt->format('Y-m-d H:i:s') === $row['applied_at'];

        if (
            $row['version'] !== ModuleMetadata::SCHEMA_VERSION
            || !hash_equals($catalog->migrationChecksum(), $row['checksum'])
            || !hash_equals($catalog->fingerprint(), $row['fingerprint'])
            || $row['status'] !== 'applied'
            || !$validAppliedAt
        ) {
            throw new IncompatibleSchemaException(
                'Existing migration journal entry does not match the approved foundation schema.',
            );
        }
    }
}
