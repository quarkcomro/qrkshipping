<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Settings;

use InvalidArgumentException;
use Stringable;

final readonly class SettingScope implements Stringable
{
    private function __construct(
        private SettingScopeType $type,
        private int $shopGroupId,
        private int $shopId,
    ) {
    }

    public static function all(): self
    {
        return new self(SettingScopeType::ALL, 0, 0);
    }

    public static function shopGroup(int $shopGroupId): self
    {
        if ($shopGroupId <= 0) {
            throw new InvalidArgumentException('Shop-group scope requires a positive shop-group ID.');
        }

        return new self(SettingScopeType::SHOP_GROUP, $shopGroupId, 0);
    }

    public static function shop(int $shopId, int $shopGroupId): self
    {
        if ($shopId <= 0 || $shopGroupId <= 0) {
            throw new InvalidArgumentException('Shop scope requires positive shop and shop-group IDs.');
        }

        return new self(SettingScopeType::SHOP, $shopGroupId, $shopId);
    }

    /**
     * Ordered from most specific to least specific.
     *
     * @return list<self>
     */
    public function resolutionChain(): array
    {
        return match ($this->type) {
            SettingScopeType::ALL => [self::all()],
            SettingScopeType::SHOP_GROUP => [$this, self::all()],
            SettingScopeType::SHOP => [$this, self::shopGroup($this->shopGroupId), self::all()],
        };
    }

    public function type(): SettingScopeType
    {
        return $this->type;
    }

    public function shopGroupId(): int
    {
        return $this->shopGroupId;
    }

    public function shopId(): int
    {
        return $this->shopId;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type
            && $this->shopGroupId === $other->shopGroupId
            && $this->shopId === $other->shopId;
    }

    public function cacheKey(): string
    {
        return implode(':', [$this->type->value, $this->shopGroupId, $this->shopId]);
    }

    public function __toString(): string
    {
        return $this->cacheKey();
    }
}
