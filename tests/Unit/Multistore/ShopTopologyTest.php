<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Multistore;

use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Multistore\ShopTopology;

final class ShopTopologyTest extends TestCase
{
    public function testReadsConfiguredShopCountFromPrestaShop(): void
    {
        self::assertSame(1, (new ShopTopology())->configuredShopCount());
    }
}
