<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Repository;

use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScopeType;
use UnexpectedValueException;

final class ScopePersistenceMapper
{
    /**
     * @return array{scope_type: string, id_shop_group: int, id_shop: int}
     */
    public function toColumns(SettingScope $scope): array
    {
        return [
            'scope_type' => $scope->type()->value,
            'id_shop_group' => $scope->shopGroupId(),
            'id_shop' => $scope->shopId(),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public function fromRow(array $row): SettingScope
    {
        $type = SettingScopeType::tryFrom((string) ($row['scope_type'] ?? ''));
        $shopGroupId = (int) ($row['id_shop_group'] ?? 0);
        $shopId = (int) ($row['id_shop'] ?? 0);

        return match ($type) {
            SettingScopeType::ALL => SettingScope::all(),
            SettingScopeType::SHOP_GROUP => SettingScope::shopGroup($shopGroupId),
            SettingScopeType::SHOP => SettingScope::shop($shopId, $shopGroupId),
            null => throw new UnexpectedValueException('Stored setting scope type is invalid.'),
        };
    }
}
