<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Application\Settings;

use InvalidArgumentException;
use Qrk\Commerce\Shipping\Domain\Money\DecimalAmount;
use Qrk\Commerce\Shipping\Domain\Settings\SettingDefinition;
use Qrk\Commerce\Shipping\Domain\Settings\SettingType;

final class SettingValueCodec
{
    public function encode(SettingDefinition $definition, mixed $rawValue): EncodedSettingValue
    {
        if (is_string($rawValue) && $rawValue === '') {
            if (!$definition->allowsEmpty()) {
                throw new InvalidArgumentException(sprintf(
                    'Setting "%s" does not allow an explicit empty value.',
                    $definition->key(),
                ));
            }

            return new EncodedSettingValue('', true);
        }

        $encoded = match ($definition->type()) {
            SettingType::STRING => $this->encodeString($rawValue),
            SettingType::BOOLEAN => $this->encodeBoolean($rawValue),
            SettingType::INTEGER => $this->encodeInteger($rawValue),
            SettingType::DECIMAL => $this->encodeDecimal($rawValue),
        };

        if ($definition->allowedValues() !== [] && !in_array($encoded, $definition->allowedValues(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Setting "%s" contains a value outside its allowed catalog.',
                $definition->key(),
            ));
        }

        return new EncodedSettingValue($encoded, false);
    }

    public function decode(SettingDefinition $definition, string $encodedValue, bool $explicitEmpty): mixed
    {
        if ($explicitEmpty) {
            if (!$definition->allowsEmpty() || $encodedValue !== '') {
                throw new InvalidArgumentException(sprintf(
                    'Stored empty state for setting "%s" is invalid.',
                    $definition->key(),
                ));
            }

            return '';
        }

        $decoded = match ($definition->type()) {
            SettingType::STRING => $encodedValue,
            SettingType::BOOLEAN => match ($encodedValue) {
                '1' => true,
                '0' => false,
                default => throw new InvalidArgumentException('Stored boolean setting is invalid.'),
            },
            SettingType::INTEGER => $this->decodeInteger($encodedValue),
            SettingType::DECIMAL => DecimalAmount::fromString($encodedValue),
        };

        $this->assertCanonicalStoredValue($definition, $encodedValue, $decoded);

        return $decoded;
    }

    private function assertCanonicalStoredValue(
        SettingDefinition $definition,
        string $encodedValue,
        mixed $decoded,
    ): void {
        if ($definition->type() === SettingType::STRING) {
            if ($encodedValue === '') {
                throw new InvalidArgumentException(
                    'Stored empty string is missing its explicit-empty marker.',
                );
            }

            if (
                $definition->allowedValues() !== []
                && !in_array($encodedValue, $definition->allowedValues(), true)
            ) {
                throw new InvalidArgumentException('Stored string setting is outside its allowed catalog.');
            }

            return;
        }

        $canonical = match ($definition->type()) {
            SettingType::BOOLEAN => $decoded === true ? '1' : '0',
            SettingType::INTEGER => (string) $decoded,
            SettingType::DECIMAL => (string) $decoded,
            SettingType::STRING => throw new \LogicException('String settings were handled separately.'),
        };

        if ($canonical !== $encodedValue) {
            throw new InvalidArgumentException('Stored setting value is not in canonical form.');
        }
    }

    private function encodeString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('String setting requires a string value.');
        }

        if (mb_strlen($value) > 65535) {
            throw new InvalidArgumentException('String setting exceeds the supported length.');
        }

        return $value;
    }

    private function encodeBoolean(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true' => '1',
                '0', 'false' => '0',
                default => throw new InvalidArgumentException('Boolean setting is invalid.'),
            };
        }

        throw new InvalidArgumentException('Boolean setting is invalid.');
    }

    private function encodeInteger(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (!is_string($value) || preg_match('/^-?(?:0|[1-9]\d*)$/D', trim($value)) !== 1) {
            throw new InvalidArgumentException('Integer setting is invalid.');
        }

        return (string) $this->decodeInteger(trim($value));
    }

    private function encodeDecimal(mixed $value): string
    {
        if ($value instanceof DecimalAmount) {
            return (string) $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Decimal setting requires a decimal string.');
        }

        return (string) DecimalAmount::fromString($value);
    }

    private function decodeInteger(string $value): int
    {
        if (preg_match('/^-?(?:0|[1-9]\d*)$/D', $value) !== 1) {
            throw new InvalidArgumentException('Stored integer setting is invalid.');
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false) {
            throw new InvalidArgumentException('Integer setting is outside the platform integer range.');
        }

        return $integer;
    }
}
