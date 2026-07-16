<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Lifecycle;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Application\Lifecycle\LifecyclePolicyAccess;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScopeType;
use RuntimeException;

final class LifecyclePolicyAccessTest extends TestCase
{
    /**
     * @return iterable<string, array{SettingScopeType, int, bool, bool, bool, bool}>
     */
    public static function accessCases(): iterable
    {
        yield 'all stores with several shops' => [
            SettingScopeType::ALL,
            3,
            true,
            true,
            false,
            true,
        ];
        yield 'single shop fallback' => [
            SettingScopeType::SHOP,
            1,
            true,
            true,
            true,
            true,
        ];
        yield 'shop context with several shops requires all stores' => [
            SettingScopeType::SHOP,
            2,
            true,
            false,
            false,
            true,
        ];
        yield 'shop group never uses the single shop fallback' => [
            SettingScopeType::SHOP_GROUP,
            1,
            true,
            false,
            false,
            true,
        ];
        yield 'employee without all-shops authorization' => [
            SettingScopeType::SHOP,
            1,
            false,
            false,
            false,
            false,
        ];
    }

    #[DataProvider('accessCases')]
    public function testEvaluatesGlobalPolicyAccess(
        SettingScopeType $scopeType,
        int $configuredShopCount,
        bool $authorizedForAllShops,
        bool $editable,
        bool $singleShopFallback,
        bool $reportsAuthorization,
    ): void {
        $access = LifecyclePolicyAccess::evaluate(
            $scopeType,
            $configuredShopCount,
            $authorizedForAllShops,
        );

        self::assertSame($editable, $access->canEdit());
        self::assertSame($singleShopFallback, $access->usesSingleShopFallback());
        self::assertSame($reportsAuthorization, $access->isAuthorizedForAllShops());
    }

    public function testFailsClosedWhenNoConfiguredShopCanBeObserved(): void
    {
        $this->expectException(RuntimeException::class);

        LifecyclePolicyAccess::evaluate(SettingScopeType::SHOP, 0, true);
    }
}
