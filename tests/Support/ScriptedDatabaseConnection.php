<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Support;

use RuntimeException;
use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;

final class ScriptedDatabaseConnection implements DatabaseConnectionPort
{
    /** @var list<string> */
    public array $executed = [];

    /** @param list<string> $failingStatements */
    public function __construct(
        private readonly array $failingStatements = [],
    ) {
    }

    public function execute(string $sql): void
    {
        $this->executed[] = $sql;

        if (in_array($sql, $this->failingStatements, true)) {
            throw new RuntimeException('Injected database failure.');
        }
    }

    public function fetchOne(string $sql): ?array
    {
        return null;
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
}
