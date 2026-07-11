<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema;

use JsonException;
use Qrk\Commerce\Shipping\ModuleMetadata;
use RuntimeException;

final class SchemaCatalog
{
    /** @var non-empty-list<TableDefinition> */
    private array $tables;

    public function __construct()
    {
        $this->tables = [
            $this->migrationTable(),
            $this->settingTable(),
            $this->secretTable(),
            $this->providerAccountTable(),
            $this->auditEventTable(),
        ];
    }

    /**
     * @return non-empty-list<TableDefinition>
     */
    public function tables(): array
    {
        return $this->tables;
    }

    /**
     * @return non-empty-list<string>
     */
    public function suffixes(): array
    {
        return array_map(
            static fn (TableDefinition $table): string => $table->suffix(),
            $this->tables,
        );
    }

    public function bySuffix(string $suffix): TableDefinition
    {
        foreach ($this->tables as $table) {
            if ($table->suffix() === $suffix) {
                return $table;
            }
        }

        throw new RuntimeException(sprintf('Unknown schema table "%s".', $suffix));
    }

    public function fingerprint(): string
    {
        return hash('sha256', $this->canonicalJson());
    }

    public function migrationChecksum(): string
    {
        return hash('sha256', ModuleMetadata::SCHEMA_VERSION . "\n" . $this->canonicalJson());
    }

    private function canonicalJson(): string
    {
        try {
            return json_encode(
                array_map(
                    static fn (TableDefinition $table): array => $table->canonical(),
                    $this->tables,
                ),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Schema catalog cannot be serialized.', previous: $exception);
        }
    }

    private function migrationTable(): TableDefinition
    {
        return new TableDefinition(
            'qrkship_schema_migration',
            [
                new ColumnDefinition('id_schema_migration', 'BIGINT UNSIGNED', false, null, true),
                new ColumnDefinition('migration_version', 'VARCHAR(32)'),
                new ColumnDefinition('migration_checksum', 'CHAR(64)'),
                new ColumnDefinition('schema_fingerprint', 'CHAR(64)'),
                new ColumnDefinition('status', 'VARCHAR(16)'),
                new ColumnDefinition('error_code', 'VARCHAR(64)', true),
                new ColumnDefinition('applied_at', 'DATETIME'),
            ],
            [
                IndexDefinition::primary(['id_schema_migration']),
                IndexDefinition::unique('uniq_qrkship_migration_version', ['migration_version']),
            ],
        );
    }

    private function settingTable(): TableDefinition
    {
        return new TableDefinition(
            'qrkship_setting',
            [
                new ColumnDefinition('id_setting', 'BIGINT UNSIGNED', false, null, true),
                new ColumnDefinition('setting_key', 'VARCHAR(128)'),
                new ColumnDefinition('value_type', 'VARCHAR(16)'),
                new ColumnDefinition('scope_type', 'VARCHAR(16)'),
                new ColumnDefinition('id_shop_group', 'INT UNSIGNED', false, '0'),
                new ColumnDefinition('id_shop', 'INT UNSIGNED', false, '0'),
                new ColumnDefinition('value_text', 'LONGTEXT', true),
                new ColumnDefinition('is_empty', 'TINYINT(1)', false, '0'),
                new ColumnDefinition('created_at', 'DATETIME'),
                new ColumnDefinition('updated_at', 'DATETIME'),
            ],
            [
                IndexDefinition::primary(['id_setting']),
                IndexDefinition::unique(
                    'uniq_qrkship_setting_scope',
                    ['setting_key', 'scope_type', 'id_shop_group', 'id_shop'],
                ),
                IndexDefinition::regular(
                    'idx_qrkship_setting_scope',
                    ['scope_type', 'id_shop_group', 'id_shop'],
                ),
            ],
        );
    }

    private function secretTable(): TableDefinition
    {
        return new TableDefinition(
            'qrkship_secret',
            [
                new ColumnDefinition('id_secret', 'BIGINT UNSIGNED', false, null, true),
                new ColumnDefinition('provider_account_id', 'BIGINT UNSIGNED', false, '0'),
                new ColumnDefinition('secret_key', 'VARCHAR(128)'),
                new ColumnDefinition('scope_type', 'VARCHAR(16)'),
                new ColumnDefinition('id_shop_group', 'INT UNSIGNED', false, '0'),
                new ColumnDefinition('id_shop', 'INT UNSIGNED', false, '0'),
                new ColumnDefinition('cipher_version', 'SMALLINT UNSIGNED'),
                new ColumnDefinition('key_id', 'VARCHAR(64)'),
                new ColumnDefinition('nonce_b64', 'VARCHAR(64)'),
                new ColumnDefinition('ciphertext_b64', 'LONGTEXT'),
                new ColumnDefinition('auth_tag_b64', 'VARCHAR(64)'),
                new ColumnDefinition('created_at', 'DATETIME'),
                new ColumnDefinition('updated_at', 'DATETIME'),
            ],
            [
                IndexDefinition::primary(['id_secret']),
                IndexDefinition::unique(
                    'uniq_qrkship_secret_locator',
                    [
                        'provider_account_id',
                        'secret_key',
                        'scope_type',
                        'id_shop_group',
                        'id_shop',
                    ],
                ),
                IndexDefinition::regular(
                    'idx_qrkship_secret_account',
                    ['provider_account_id', 'id_shop'],
                ),
            ],
        );
    }

    private function providerAccountTable(): TableDefinition
    {
        return new TableDefinition(
            'qrkship_provider_account',
            [
                new ColumnDefinition('id_provider_account', 'BIGINT UNSIGNED', false, null, true),
                new ColumnDefinition('provider_code', 'VARCHAR(64)'),
                new ColumnDefinition('id_shop', 'INT UNSIGNED'),
                new ColumnDefinition('account_label', 'VARCHAR(160)'),
                new ColumnDefinition('status', 'VARCHAR(32)'),
                new ColumnDefinition('created_at', 'DATETIME'),
                new ColumnDefinition('updated_at', 'DATETIME'),
            ],
            [
                IndexDefinition::primary(['id_provider_account']),
                IndexDefinition::unique(
                    'uniq_qrkship_provider_shop',
                    ['provider_code', 'id_shop'],
                ),
                IndexDefinition::regular(
                    'idx_qrkship_provider_status',
                    ['provider_code', 'status'],
                ),
            ],
        );
    }

    private function auditEventTable(): TableDefinition
    {
        return new TableDefinition(
            'qrkship_audit_event',
            [
                new ColumnDefinition('id_audit_event', 'BIGINT UNSIGNED', false, null, true),
                new ColumnDefinition('event_code', 'VARCHAR(96)'),
                new ColumnDefinition('severity', 'VARCHAR(16)'),
                new ColumnDefinition('scope_type', 'VARCHAR(16)'),
                new ColumnDefinition('id_shop_group', 'INT UNSIGNED', false, '0'),
                new ColumnDefinition('id_shop', 'INT UNSIGNED', false, '0'),
                new ColumnDefinition('actor_type', 'VARCHAR(32)'),
                new ColumnDefinition('actor_id', 'BIGINT UNSIGNED', false, '0'),
                new ColumnDefinition('subject_type', 'VARCHAR(64)'),
                new ColumnDefinition('subject_id', 'VARCHAR(128)'),
                new ColumnDefinition('correlation_id', 'VARCHAR(64)'),
                new ColumnDefinition('metadata_text', 'LONGTEXT', true),
                new ColumnDefinition('occurred_at', 'DATETIME'),
            ],
            [
                IndexDefinition::primary(['id_audit_event']),
                IndexDefinition::regular(
                    'idx_qrkship_audit_time',
                    ['occurred_at', 'id_audit_event'],
                ),
                IndexDefinition::regular(
                    'idx_qrkship_audit_event',
                    ['event_code', 'occurred_at'],
                ),
                IndexDefinition::regular(
                    'idx_qrkship_audit_subject',
                    ['subject_type', 'subject_id'],
                ),
            ],
        );
    }
}
