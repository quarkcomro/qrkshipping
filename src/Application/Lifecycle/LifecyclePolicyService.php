<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Application\Lifecycle;

use Qrk\Commerce\Shipping\Application\Settings\SettingsCatalog;
use Qrk\Commerce\Shipping\Application\Settings\SettingsService;
use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;

final class LifecyclePolicyService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {
    }

    public function current(): LifecyclePolicy
    {
        $scope = SettingScope::all();

        return new LifecyclePolicy(
            (bool) $this->settings->resolve(
                SettingsCatalog::LIFECYCLE_PURGE_ON_UNINSTALL,
                $scope,
            )->value(),
            (bool) $this->settings->resolve(
                SettingsCatalog::LIFECYCLE_RESET_TO_DEFAULTS,
                $scope,
            )->value(),
        );
    }

    public function save(
        bool $purgeOnUninstall,
        bool $resetToDefaults,
        AuditActor $actor,
    ): void {
        $this->settings->setMany(
            [
                SettingsCatalog::LIFECYCLE_PURGE_ON_UNINSTALL => $purgeOnUninstall,
                SettingsCatalog::LIFECYCLE_RESET_TO_DEFAULTS => $resetToDefaults,
            ],
            SettingScope::all(),
            $actor,
        );
    }
}
