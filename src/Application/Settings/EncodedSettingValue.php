<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Application\Settings;

final readonly class EncodedSettingValue
{
    public function __construct(
        private string $value,
        private bool $explicitEmpty,
    ) {
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isExplicitEmpty(): bool
    {
        return $this->explicitEmpty;
    }
}
