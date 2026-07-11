<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Support;

use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;

final class FailureInjectingDatabaseConnection implements DatabaseConnectionPort
{
    /** @var list<string> */
    public array $executed = [];
    /** @var array<string, true> */
    private array $tables = [];
    private int $affectedRows = 0;

    public function execute(string $sql): void
    {
        $this->executed[] = $sql;
        if (preg_match('/^CREATE TABLE `([^`]+)`/D', $sql, $match) === 1) {
            $this->tables[$match[1]] = true;
            $this->affectedRows = 0;

            return;
        }

        if (preg_match('/^DROP TABLE IF EXISTS `([^`]+)`/D', $sql, $match) === 1) {
            unset($this->tables[$match[1]]);
            $this->affectedRows = 0;

            return;
        }

        $this->affectedRows = 1;
    }

    public function fetchOne(string $sql): ?array
    {
        if (str_contains($sql, '@@collation_database')) {
            return ['collation' => 'utf8mb4_unicode_ci'];
        }

        if (str_contains($sql, 'INFORMATION_SCHEMA`.`TABLES')) {
            if (preg_match("/`TABLE_NAME` = '([^']+)'/", $sql, $match) !== 1) {
                return null;
            }

            return isset($this->tables[$match[1]]) ? ['TABLE_NAME' => $match[1]] : null;
        }

        return null;
    }

    public function fetchAll(string $sql): array
    {
        $row = $this->fetchOne($sql);

        return $row === null ? [] : [$row];
    }

    public function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    public function lastInsertId(): int
    {
        return 1;
    }

    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    /** @return list<string> */
    public function tableNames(): array
    {
        $names = array_keys($this->tables);
        sort($names);

        return $names;
    }
}
