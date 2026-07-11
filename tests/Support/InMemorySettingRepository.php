<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Support;

use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Domain\Settings\StoredSetting;
use Qrk\Commerce\Shipping\Port\Persistence\SettingRepositoryPort;

final class InMemorySettingRepository implements SettingRepositoryPort
{
    /** @var array<string, StoredSetting> */
    private array $items = [];
    public int $findCalls = 0;

    public function find(string $key, SettingScope $scope): ?StoredSetting
    {
        ++$this->findCalls;

        return $this->items[$this->key($key, $scope)] ?? null;
    }

    public function save(StoredSetting $setting): void
    {
        $this->items[$this->key($setting->key(), $setting->scope())] = $setting;
    }

    public function delete(string $key, SettingScope $scope): void
    {
        unset($this->items[$this->key($key, $scope)]);
    }

    private function key(string $key, SettingScope $scope): string
    {
        return $key . '|' . $scope->cacheKey();
    }
}
