<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Port\Persistence;

use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Domain\Settings\StoredSetting;

interface SettingRepositoryPort
{
    public function find(string $key, SettingScope $scope): ?StoredSetting;

    public function save(StoredSetting $setting): void;

    public function delete(string $key, SettingScope $scope): void;
}
