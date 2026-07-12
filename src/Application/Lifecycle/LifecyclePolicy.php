<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Application\Lifecycle;

final readonly class LifecyclePolicy
{
    public function __construct(
        private bool $purgeOnUninstall,
        private bool $resetToDefaults,
    ) {
    }

    public static function defaults(): self
    {
        return new self(false, false);
    }

    public function purgeOnUninstall(): bool
    {
        return $this->purgeOnUninstall;
    }

    public function resetToDefaults(): bool
    {
        return $this->resetToDefaults;
    }

    public function purgeFor(LifecycleOperation $operation): bool
    {
        return match ($operation) {
            LifecycleOperation::UNINSTALL => $this->purgeOnUninstall,
            LifecycleOperation::RESET => $this->resetToDefaults,
        };
    }
}
