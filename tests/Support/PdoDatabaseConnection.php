<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Support;

use PDO;
use PDOException;
use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;
use RuntimeException;

final class PdoDatabaseConnection implements DatabaseConnectionPort
{
    private int $affectedRows = 0;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function execute(string $sql): void
    {
        try {
            $affectedRows = $this->pdo->exec($sql);
        } catch (PDOException $exception) {
            throw new RuntimeException('PDO database statement failed.', previous: $exception);
        }

        if ($affectedRows === false) {
            throw new RuntimeException('PDO database statement failed without an exception.');
        }

        $this->affectedRows = $affectedRows;
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
        try {
            $statement = $this->pdo->query($sql);
        } catch (PDOException $exception) {
            throw new RuntimeException('PDO database query failed.', previous: $exception);
        }

        if ($statement === false) {
            throw new RuntimeException('PDO database query failed without an exception.');
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        /** @var list<array<string, mixed>> $rows */
        return $rows;
    }

    public function quote(string $value): string
    {
        $quoted = $this->pdo->quote($value);
        if ($quoted === false) {
            throw new RuntimeException('PDO could not quote a database value.');
        }

        return $quoted;
    }

    public function lastInsertId(): int
    {
        $id = $this->pdo->lastInsertId();
        if ($id === false || preg_match('/^\d+$/D', $id) !== 1) {
            throw new RuntimeException('PDO returned an invalid last-insert identifier.');
        }

        return (int) $id;
    }

    public function affectedRows(): int
    {
        return $this->affectedRows;
    }
}
