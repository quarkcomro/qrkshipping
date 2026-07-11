<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Multistore;

use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;

final readonly class BackOfficeShopContext
{
    /**
     * @param list<int> $associatedShopIds
     */
    public function __construct(
        private SettingScope $scope,
        private string $shopName,
        private array $associatedShopIds,
    ) {
    }

    public function scope(): SettingScope
    {
        return $this->scope;
    }

    public function shopName(): string
    {
        return $this->shopName;
    }

    /**
     * @return list<int>
     */
    public function associatedShopIds(): array
    {
        return $this->associatedShopIds;
    }

    public function isSingleShop(): bool
    {
        return $this->scope->shopId() > 0;
    }
}
