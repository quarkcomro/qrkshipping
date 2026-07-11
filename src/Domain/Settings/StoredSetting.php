<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Settings;

use InvalidArgumentException;

final readonly class StoredSetting
{
    public function __construct(
        private string $key,
        private SettingType $type,
        private SettingScope $scope,
        private string $encodedValue,
        private bool $explicitEmpty,
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{2,127}$/D', $key) !== 1) {
            throw new InvalidArgumentException('Stored setting key is invalid.');
        }

        if ($explicitEmpty && $encodedValue !== '') {
            throw new InvalidArgumentException('Explicit-empty setting rows cannot contain a non-empty value.');
        }
    }

    public function key(): string
    {
        return $this->key;
    }

    public function type(): SettingType
    {
        return $this->type;
    }

    public function scope(): SettingScope
    {
        return $this->scope;
    }

    public function encodedValue(): string
    {
        return $this->encodedValue;
    }

    public function isExplicitEmpty(): bool
    {
        return $this->explicitEmpty;
    }
}
