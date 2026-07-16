<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Multistore;

final class ShopTopology
{
    public function configuredShopCount(): int
    {
        return (int) \Shop::getTotalShops(false);
    }
}
