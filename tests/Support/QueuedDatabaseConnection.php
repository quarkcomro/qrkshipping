<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Support;

use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;

final class QueuedDatabaseConnection implements DatabaseConnectionPort
{
    /** @var list<array<string, mixed>|null> */
    private array $fetchOneResponses;

    /**
     * @param list<array<string, mixed>|null> $fetchOneResponses
     */
    public function __construct(array $fetchOneResponses)
    {
        $this->fetchOneResponses = $fetchOneResponses;
    }

    public function execute(string $sql): void
    {
    }

    public function fetchOne(string $sql): ?array
    {
        if ($this->fetchOneResponses === []) {
            return null;
        }

        return array_shift($this->fetchOneResponses);
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
