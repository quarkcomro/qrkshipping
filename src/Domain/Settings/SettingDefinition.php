<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Settings;

use InvalidArgumentException;

final readonly class SettingDefinition
{
    /**
     * @param list<string> $allowedValues
     */
    public function __construct(
        private string $key,
        private SettingType $type,
        private string $defaultEncodedValue,
        private bool $allowEmpty = false,
        private array $allowedValues = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{2,127}$/D', $key) !== 1) {
            throw new InvalidArgumentException('Setting key is invalid.');
        }

        if ($allowedValues !== [] && $type !== SettingType::STRING) {
            throw new InvalidArgumentException('Allowed-value catalog is supported only for string settings.');
        }

        if (!$allowEmpty && $defaultEncodedValue === '') {
            throw new InvalidArgumentException('Non-empty setting definition cannot have an empty default.');
        }

        if ($allowedValues !== [] && !in_array($defaultEncodedValue, $allowedValues, true)) {
            throw new InvalidArgumentException('Setting default is not present in the allowed-value catalog.');
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

    public function defaultEncodedValue(): string
    {
        return $this->defaultEncodedValue;
    }

    public function allowsEmpty(): bool
    {
        return $this->allowEmpty;
    }

    /**
     * @return list<string>
     */
    public function allowedValues(): array
    {
        return $this->allowedValues;
    }
}
