<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Persistence;

use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;

final class PrestaShopDatabaseConnection implements DatabaseConnectionPort
{
    public function execute(string $sql): void
    {
        $result = \Db::getInstance()->execute($sql);
        if ($result !== true) {
            throw new DatabaseOperationException($this->safeError('Database statement failed.'));
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql): ?array
    {
        $rows = $this->fetchAll($sql);

        return $rows[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql): array
    {
        $rows = \Db::getInstance()->executeS($sql, true, false);
        if ($rows === false) {
            throw new DatabaseOperationException($this->safeError('Database query failed.'));
        }

        return array_values($rows);
    }

    public function quote(string $value): string
    {
        return "'" . \Db::getInstance()->escape($value, true, true) . "'";
    }

    public function lastInsertId(): int
    {
        return (int) \Db::getInstance()->Insert_ID();
    }

    public function affectedRows(): int
    {
        return (int) \Db::getInstance()->Affected_Rows();
    }

    private function safeError(string $fallback): string
    {
        $message = trim((string) \Db::getInstance()->getMsgError());

        return $message === '' ? $fallback : $fallback . ' Database error code was reported by the platform.';
    }
}
