<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Settings;

final readonly class ResolvedSetting
{
    public function __construct(
        private SettingDefinition $definition,
        private mixed $value,
        private ?SettingScope $sourceScope,
        private bool $explicitEmpty,
    ) {
    }

    public function definition(): SettingDefinition
    {
        return $this->definition;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function sourceScope(): ?SettingScope
    {
        return $this->sourceScope;
    }

    public function usesDefault(): bool
    {
        return $this->sourceScope === null;
    }

    public function isInheritedAt(SettingScope $requestedScope): bool
    {
        return $this->sourceScope !== null && !$this->sourceScope->equals($requestedScope);
    }

    public function isExplicitEmpty(): bool
    {
        return $this->explicitEmpty;
    }
}
