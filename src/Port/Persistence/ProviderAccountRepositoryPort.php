<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Port\Persistence;

use Qrk\Commerce\Shipping\Domain\Provider\ProviderAccount;
use Qrk\Commerce\Shipping\Domain\Provider\ProviderCode;

interface ProviderAccountRepositoryPort
{
    public function find(ProviderCode $providerCode, int $shopId): ?ProviderAccount;

    public function saveDraft(ProviderCode $providerCode, int $shopId, string $label): ProviderAccount;
}
