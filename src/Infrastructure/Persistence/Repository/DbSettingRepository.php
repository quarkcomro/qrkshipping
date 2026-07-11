<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Repository;

use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Domain\Settings\SettingType;
use Qrk\Commerce\Shipping\Domain\Settings\StoredSetting;
use Qrk\Commerce\Shipping\Port\Clock\ClockPort;
use Qrk\Commerce\Shipping\Port\Persistence\SettingRepositoryPort;
use UnexpectedValueException;

final class DbSettingRepository implements SettingRepositoryPort
{
    private readonly string $table;

    public function __construct(
        private readonly DatabaseConnectionPort $connection,
        private readonly ScopePersistenceMapper $scopeMapper,
        private readonly ClockPort $clock,
        string $databasePrefix,
    ) {
        self::assertPrefix($databasePrefix);
        $this->table = $databasePrefix . 'qrkship_setting';
    }

    public function find(string $key, SettingScope $scope): ?StoredSetting
    {
        $where = $this->scopeWhere($scope);
        $row = $this->connection->fetchOne(sprintf(
            'SELECT `setting_key`, `value_type`, `scope_type`, `id_shop_group`, `id_shop`, '
            . '`value_text`, `is_empty` FROM `%s` WHERE `setting_key` = %s AND %s',
            $this->table,
            $this->connection->quote($key),
            $where,
        ));

        if ($row === null) {
            return null;
        }

        $type = SettingType::tryFrom((string) ($row['value_type'] ?? ''));
        if ($type === null) {
            throw new UnexpectedValueException('Stored setting type is invalid.');
        }

        $emptyFlag = (string) ($row['is_empty'] ?? '');
        if (!in_array($emptyFlag, ['0', '1'], true)) {
            throw new UnexpectedValueException('Stored setting empty flag is invalid.');
        }

        return new StoredSetting(
            (string) ($row['setting_key'] ?? ''),
            $type,
            $this->scopeMapper->fromRow($row),
            (string) ($row['value_text'] ?? ''),
            $emptyFlag === '1',
        );
    }

    public function save(StoredSetting $setting): void
    {
        $scope = $this->scopeMapper->toColumns($setting->scope());
        $now = $this->connection->quote($this->clock->now()->format('Y-m-d H:i:s'));
        $value = $this->connection->quote($setting->encodedValue());
        $type = $this->connection->quote($setting->type()->value);
        $scopeType = $this->connection->quote($scope['scope_type']);
        $key = $this->connection->quote($setting->key());
        $isEmpty = $setting->isExplicitEmpty() ? 1 : 0;

        $this->connection->execute(sprintf(
            'INSERT INTO `%s` '
            . '(`setting_key`, `value_type`, `scope_type`, `id_shop_group`, `id_shop`, '
            . '`value_text`, `is_empty`, `created_at`, `updated_at`) '
            . 'VALUES (%s, %s, %s, %d, %d, %s, %d, %s, %s) '
            . 'ON DUPLICATE KEY UPDATE '
            . '`value_type` = %s, `value_text` = %s, `is_empty` = %d, `updated_at` = %s',
            $this->table,
            $key,
            $type,
            $scopeType,
            $scope['id_shop_group'],
            $scope['id_shop'],
            $value,
            $isEmpty,
            $now,
            $now,
            $type,
            $value,
            $isEmpty,
            $now,
        ));
    }

    public function delete(string $key, SettingScope $scope): void
    {
        $this->connection->execute(sprintf(
            'DELETE FROM `%s` WHERE `setting_key` = %s AND %s',
            $this->table,
            $this->connection->quote($key),
            $this->scopeWhere($scope),
        ));
    }

    private function scopeWhere(SettingScope $scope): string
    {
        $columns = $this->scopeMapper->toColumns($scope);

        return sprintf(
            '`scope_type` = %s AND `id_shop_group` = %d AND `id_shop` = %d',
            $this->connection->quote($columns['scope_type']),
            $columns['id_shop_group'],
            $columns['id_shop'],
        );
    }

    private static function assertPrefix(string $databasePrefix): void
    {
        if (preg_match('/^[A-Za-z0-9_]*$/D', $databasePrefix) !== 1) {
            throw new \InvalidArgumentException('Database prefix is invalid.');
        }
    }
}
