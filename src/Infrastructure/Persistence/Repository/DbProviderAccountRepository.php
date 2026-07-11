<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;
use Qrk\Commerce\Shipping\Domain\Provider\ProviderAccount;
use Qrk\Commerce\Shipping\Domain\Provider\ProviderAccountStatus;
use Qrk\Commerce\Shipping\Domain\Provider\ProviderCode;
use Qrk\Commerce\Shipping\Port\Clock\ClockPort;
use Qrk\Commerce\Shipping\Port\Persistence\ProviderAccountRepositoryPort;
use UnexpectedValueException;

final class DbProviderAccountRepository implements ProviderAccountRepositoryPort
{
    private readonly string $table;

    public function __construct(
        private readonly DatabaseConnectionPort $connection,
        private readonly ClockPort $clock,
        string $databasePrefix,
    ) {
        self::assertPrefix($databasePrefix);
        $this->table = $databasePrefix . 'qrkship_provider_account';
    }

    public function find(ProviderCode $providerCode, int $shopId): ?ProviderAccount
    {
        $row = $this->connection->fetchOne(sprintf(
            'SELECT `id_provider_account`, `provider_code`, `id_shop`, `account_label`, '
            . '`status`, `created_at`, `updated_at` FROM `%s` '
            . 'WHERE `provider_code` = %s AND `id_shop` = %d',
            $this->table,
            $this->connection->quote($providerCode->value()),
            $shopId,
        ));

        if ($row === null) {
            return null;
        }

        $status = ProviderAccountStatus::tryFrom((string) ($row['status'] ?? ''));
        if ($status === null) {
            throw new UnexpectedValueException('Stored provider-account status is invalid.');
        }

        $id = (int) ($row['id_provider_account'] ?? 0);
        $storedProviderCode = ProviderCode::fromString((string) ($row['provider_code'] ?? ''));
        $storedShopId = (int) ($row['id_shop'] ?? 0);

        if (
            $id <= 0
            || $storedProviderCode->value() !== $providerCode->value()
            || $storedShopId !== $shopId
        ) {
            throw new UnexpectedValueException('Stored provider-account identity is invalid.');
        }

        return new ProviderAccount(
            $id,
            $storedProviderCode,
            $storedShopId,
            (string) ($row['account_label'] ?? ''),
            $status,
            $this->parseUtcDateTime((string) ($row['created_at'] ?? '')),
            $this->parseUtcDateTime((string) ($row['updated_at'] ?? '')),
        );
    }

    public function saveDraft(ProviderCode $providerCode, int $shopId, string $label): ProviderAccount
    {
        $now = $this->connection->quote($this->clock->now()->format('Y-m-d H:i:s'));
        $provider = $this->connection->quote($providerCode->value());
        $quotedLabel = $this->connection->quote($label);
        $status = $this->connection->quote(ProviderAccountStatus::DRAFT->value);

        $this->connection->execute(sprintf(
            'INSERT INTO `%s` '
            . '(`provider_code`, `id_shop`, `account_label`, `status`, `created_at`, `updated_at`) '
            . 'VALUES (%s, %d, %s, %s, %s, %s) '
            . 'ON DUPLICATE KEY UPDATE '
            . '`account_label` = %s, `status` = %s, `updated_at` = %s',
            $this->table,
            $provider,
            $shopId,
            $quotedLabel,
            $status,
            $now,
            $now,
            $quotedLabel,
            $status,
            $now,
        ));

        return $this->find($providerCode, $shopId)
            ?? throw new UnexpectedValueException('Provider account could not be reloaded after persistence.');
    }

    private function parseUtcDateTime(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value,
            new DateTimeZone('UTC'),
        );
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d H:i:s') !== $value
        ) {
            throw new UnexpectedValueException('Stored provider-account timestamp is invalid.');
        }

        return $date;
    }

    private static function assertPrefix(string $databasePrefix): void
    {
        if (preg_match('/^[A-Za-z0-9_]*$/D', $databasePrefix) !== 1) {
            throw new \InvalidArgumentException('Database prefix is invalid.');
        }
    }
}
