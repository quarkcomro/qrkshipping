<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Settings;

enum SettingScopeType: string
{
    case ALL = 'all';
    case SHOP_GROUP = 'shop_group';
    case SHOP = 'shop';
}
