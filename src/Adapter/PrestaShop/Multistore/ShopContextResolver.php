<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Multistore;

use PrestaShop\PrestaShop\Core\Context\ShopContext;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use RuntimeException;

final class ShopContextResolver
{
    public function __construct(
        private readonly ShopContext $shopContext,
    ) {
    }

    public function current(): BackOfficeShopContext
    {
        $associatedShopIds = array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            $this->shopContext->getAssociatedShopIds(),
        ));

        if ($this->shopContext->isAllShopContext()) {
            return new BackOfficeShopContext(
                SettingScope::all(),
                '',
                $associatedShopIds,
            );
        }

        if ($this->shopContext->isShopGroupContext()) {
            $groupId = $this->shopContext->getShopGroupId();
            if ($groupId <= 0) {
                throw new RuntimeException('PrestaShop returned an invalid shop-group context.');
            }

            return new BackOfficeShopContext(
                SettingScope::shopGroup($groupId),
                '',
                $associatedShopIds,
            );
        }

        if ($this->shopContext->isSingleShopContext()) {
            return new BackOfficeShopContext(
                SettingScope::shop(
                    $this->shopContext->getId(),
                    $this->shopContext->getShopGroupId(),
                ),
                $this->shopContext->getName(),
                $associatedShopIds,
            );
        }

        throw new RuntimeException('PrestaShop returned an unsupported shop context.');
    }
}
