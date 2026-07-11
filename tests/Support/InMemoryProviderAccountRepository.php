<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use Qrk\Commerce\Shipping\Domain\Provider\ProviderAccount;
use Qrk\Commerce\Shipping\Domain\Provider\ProviderAccountStatus;
use Qrk\Commerce\Shipping\Domain\Provider\ProviderCode;
use Qrk\Commerce\Shipping\Port\Persistence\ProviderAccountRepositoryPort;

final class InMemoryProviderAccountRepository implements ProviderAccountRepositoryPort
{
    /** @var array<string, ProviderAccount> */
    private array $items = [];
    private int $nextId = 1;

    public function find(ProviderCode $providerCode, int $shopId): ?ProviderAccount
    {
        return $this->items[$this->key($providerCode, $shopId)] ?? null;
    }

    public function saveDraft(ProviderCode $providerCode, int $shopId, string $label): ProviderAccount
    {
        $key = $this->key($providerCode, $shopId);
        $existing = $this->items[$key] ?? null;
        $now = new DateTimeImmutable('2026-07-11 12:00:00', new DateTimeZone('UTC'));
        $account = new ProviderAccount(
            $existing?->id() ?? $this->nextId++,
            $providerCode,
            $shopId,
            $label,
            ProviderAccountStatus::DRAFT,
            $existing?->createdAt() ?? $now,
            $now,
        );
        $this->items[$key] = $account;

        return $account;
    }

    private function key(ProviderCode $providerCode, int $shopId): string
    {
        return $providerCode->value() . '|' . $shopId;
    }
}
