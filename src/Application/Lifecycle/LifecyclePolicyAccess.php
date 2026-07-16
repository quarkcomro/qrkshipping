<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Application\Lifecycle;

use RuntimeException;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScopeType;

final readonly class LifecyclePolicyAccess
{
    private function __construct(
        private bool $editable,
        private bool $singleShopFallback,
        private bool $authorizedForAllShops,
    ) {
    }

    public static function evaluate(
        SettingScopeType $scopeType,
        int $configuredShopCount,
        bool $authorizedForAllShops,
    ): self {
        if ($configuredShopCount < 1) {
            throw new RuntimeException('At least one configured shop is required.');
        }

        $singleShopFallback = $scopeType === SettingScopeType::SHOP && $configuredShopCount < 2;

        return new self(
            $authorizedForAllShops
                && ($scopeType === SettingScopeType::ALL || $singleShopFallback),
            $authorizedForAllShops && $singleShopFallback,
            $authorizedForAllShops,
        );
    }

    public function canEdit(): bool
    {
        return $this->editable;
    }

    public function usesSingleShopFallback(): bool
    {
        return $this->singleShopFallback;
    }

    public function isAuthorizedForAllShops(): bool
    {
        return $this->authorizedForAllShops;
    }
}
