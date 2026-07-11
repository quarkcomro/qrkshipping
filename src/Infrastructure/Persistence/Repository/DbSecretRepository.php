<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Repository;

use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;
use Qrk\Commerce\Shipping\Domain\Security\EncryptedSecret;
use Qrk\Commerce\Shipping\Domain\Security\SecretLocator;
use Qrk\Commerce\Shipping\Port\Clock\ClockPort;
use Qrk\Commerce\Shipping\Port\Persistence\SecretRepositoryPort;

final class DbSecretRepository implements SecretRepositoryPort
{
    private readonly string $table;

    public function __construct(
        private readonly DatabaseConnectionPort $connection,
        private readonly ScopePersistenceMapper $scopeMapper,
        private readonly ClockPort $clock,
        string $databasePrefix,
    ) {
        self::assertPrefix($databasePrefix);
        $this->table = $databasePrefix . 'qrkship_secret';
    }

    public function find(SecretLocator $locator): ?EncryptedSecret
    {
        $row = $this->connection->fetchOne(sprintf(
            'SELECT `cipher_version`, `key_id`, `nonce_b64`, `ciphertext_b64`, `auth_tag_b64` '
            . 'FROM `%s` WHERE %s',
            $this->table,
            $this->locatorWhere($locator),
        ));

        if ($row === null) {
            return null;
        }

        return new EncryptedSecret(
            (int) ($row['cipher_version'] ?? 0),
            (string) ($row['key_id'] ?? ''),
            (string) ($row['nonce_b64'] ?? ''),
            (string) ($row['ciphertext_b64'] ?? ''),
            (string) ($row['auth_tag_b64'] ?? ''),
        );
    }

    public function save(SecretLocator $locator, EncryptedSecret $secret): void
    {
        $scope = $this->scopeMapper->toColumns($locator->scope());
        $now = $this->connection->quote($this->clock->now()->format('Y-m-d H:i:s'));
        $keyId = $this->connection->quote($secret->keyId());
        $nonce = $this->connection->quote($secret->nonceBase64());
        $ciphertext = $this->connection->quote($secret->ciphertextBase64());
        $tag = $this->connection->quote($secret->authTagBase64());

        $this->connection->execute(sprintf(
            'INSERT INTO `%s` '
            . '(`provider_account_id`, `secret_key`, `scope_type`, `id_shop_group`, `id_shop`, '
            . '`cipher_version`, `key_id`, `nonce_b64`, `ciphertext_b64`, `auth_tag_b64`, '
            . '`created_at`, `updated_at`) '
            . 'VALUES (%d, %s, %s, %d, %d, %d, %s, %s, %s, %s, %s, %s) '
            . 'ON DUPLICATE KEY UPDATE '
            . '`cipher_version` = %d, `key_id` = %s, `nonce_b64` = %s, '
            . '`ciphertext_b64` = %s, `auth_tag_b64` = %s, `updated_at` = %s',
            $this->table,
            $locator->providerAccountId(),
            $this->connection->quote($locator->secretKey()),
            $this->connection->quote($scope['scope_type']),
            $scope['id_shop_group'],
            $scope['id_shop'],
            $secret->cipherVersion(),
            $keyId,
            $nonce,
            $ciphertext,
            $tag,
            $now,
            $now,
            $secret->cipherVersion(),
            $keyId,
            $nonce,
            $ciphertext,
            $tag,
            $now,
        ));
    }

    public function delete(SecretLocator $locator): void
    {
        $this->connection->execute(sprintf(
            'DELETE FROM `%s` WHERE %s',
            $this->table,
            $this->locatorWhere($locator),
        ));
    }

    public function deleteAll(): int
    {
        $this->connection->execute(sprintf('DELETE FROM `%s`', $this->table));

        return $this->connection->affectedRows();
    }

    private function locatorWhere(SecretLocator $locator): string
    {
        $scope = $this->scopeMapper->toColumns($locator->scope());

        return sprintf(
            '`provider_account_id` = %d AND `secret_key` = %s AND '
            . '`scope_type` = %s AND `id_shop_group` = %d AND `id_shop` = %d',
            $locator->providerAccountId(),
            $this->connection->quote($locator->secretKey()),
            $this->connection->quote($scope['scope_type']),
            $scope['id_shop_group'],
            $scope['id_shop'],
        );
    }

    private static function assertPrefix(string $databasePrefix): void
    {
        if (preg_match('/^[A-Za-z0-9_]*$/D', $databasePrefix) !== 1) {
            throw new \InvalidArgumentException('Database prefix is invalid.');
        }
    }
}
