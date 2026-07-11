<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Port\Persistence;

interface DatabaseConnectionPort
{
    public function execute(string $sql): void;

    /**
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql): array;

    public function quote(string $value): string;

    public function lastInsertId(): int;

    public function affectedRows(): int;
}
