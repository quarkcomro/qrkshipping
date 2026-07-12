<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Qrk\Commerce\Shipping\Adapter\PrestaShop\Install\FoundationFactory;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Install\RuntimeRequirementFailure;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Install\RuntimeRequirementsChecker;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Lifecycle\LifecycleOperationDetector;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Persistence\PrestaShopDatabaseConnection;
use Qrk\Commerce\Shipping\Application\Lifecycle\LifecycleOperation;
use Qrk\Commerce\Shipping\Controller\Admin\DashboardController;
use Qrk\Commerce\Shipping\Controller\Admin\DiagnosticsController;
use Qrk\Commerce\Shipping\Controller\Admin\HelpController;
use Qrk\Commerce\Shipping\Controller\Admin\PreferencesController;
use Qrk\Commerce\Shipping\ModuleMetadata;

final class QrkShipping extends CarrierModule
{
    private const ADMIN_DOMAIN = 'Modules.Qrkshipping.Admin';
    private const ERROR_DOMAIN = 'Modules.Qrkshipping.Errors';

    private static bool $resetInProgress = false;

    public function __construct()
    {
        $this->name = ModuleMetadata::NAME;
        $this->version = ModuleMetadata::VERSION;
        $this->author = 'QUARK';
        $this->tab = 'shipping_logistics';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->is_configurable = 1;
        $this->multistoreCompatibility = self::MULTISTORE_COMPATIBILITY_YES;
        $this->ps_versions_compliancy = [
            'min' => ModuleMetadata::MIN_PRESTASHOP_VERSION,
            'max' => '9.99.99',
        ];

        parent::__construct();

        $this->displayName = $this->trans('QRK Shipping', [], self::ADMIN_DOMAIN);
        $this->description = $this->trans(
            'Provider-neutral shipping foundation. This increment does not create carriers or contact providers.',
            [],
            self::ADMIN_DOMAIN,
        );
        $this->confirmUninstall = $this->trans(
            'Uninstall QRK Shipping? Encrypted secrets are always deleted. '
                . 'Depending on the global lifecycle policy, all QRK Shipping tables and data '
                . 'may also be permanently deleted.',
            [],
            self::ADMIN_DOMAIN,
        );
        $this->tabs = $this->buildTabs();
    }

    public function install(): bool
    {
        $failures = (new RuntimeRequirementsChecker(new PrestaShopDatabaseConnection()))->check();
        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->_errors[] = $this->requirementFailureMessage($failure);
            }

            return false;
        }

        if (!parent::install()) {
            return false;
        }

        if (!$this->registerHook('actionBeforeResetModule')) {
            $this->_errors[] = $this->trans(
                'The QRK Shipping reset lifecycle hook could not be registered.',
                [],
                self::ERROR_DOMAIN,
            );
            parent::uninstall();

            return false;
        }

        try {
            FoundationFactory::installer()->install();
        } catch (Throwable) {
            $this->_errors[] = $this->trans(
                'The QRK Shipping foundation could not be installed safely. No incompatible schema was overwritten.',
                [],
                self::ERROR_DOMAIN,
            );

            PrestaShopLogger::addLog(
                'QRK Shipping foundation installation failed; rollback was requested.',
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                null,
                'Module',
                (int) $this->id,
                true,
            );

            parent::uninstall();

            return false;
        }

        return true;
    }

    public function uninstall(): bool
    {
        $isReset = self::$resetInProgress || (new LifecycleOperationDetector())->isResetFor($this->name);
        self::$resetInProgress = false;

        try {
            $operation = $isReset ? LifecycleOperation::RESET : LifecycleOperation::UNINSTALL;
            $policy = FoundationFactory::lifecyclePolicy()->current();
            $purgeAllData = $policy->purgeFor($operation);
        } catch (Throwable) {
            $message = $isReset
                ? 'Reset was stopped because the global lifecycle policy could not be read safely.'
                : 'Uninstall was stopped because the global lifecycle policy could not be read safely.';
            $this->_errors[] = $this->trans($message, [], self::ERROR_DOMAIN);

            return false;
        }

        return $this->uninstallFoundation(
            $purgeAllData,
            $operation,
        );
    }

    /**
     * Direct reset path used by PrestaShop callers that request keep-data reset semantics.
     * The standard Back Office reset path is identified through actionBeforeResetModule.
     */
    public function reset(): bool
    {
        self::$resetInProgress = false;

        try {
            $policy = FoundationFactory::lifecyclePolicy()->current();
            $resetToDefaults = $policy->purgeFor(LifecycleOperation::RESET);
            FoundationFactory::uninstaller()->uninstall($resetToDefaults, LifecycleOperation::RESET);
            if ($resetToDefaults) {
                FoundationFactory::installer()->install();
            }
        } catch (Throwable) {
            $this->_errors[] = $this->trans(
                'The lifecycle operation was stopped because QRK Shipping data could not be processed safely.',
                [],
                self::ERROR_DOMAIN,
            );

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function hookActionBeforeResetModule(array $parameters): void
    {
        if (($parameters['moduleName'] ?? null) === $this->name) {
            self::$resetInProgress = true;
        }
    }

    private function uninstallFoundation(bool $purgeAllData, LifecycleOperation $operation): bool
    {
        try {
            FoundationFactory::uninstaller()->uninstall($purgeAllData, $operation);
        } catch (Throwable) {
            $this->_errors[] = $this->trans(
                'The lifecycle operation was stopped because QRK Shipping data could not be processed safely.',
                [],
                self::ERROR_DOMAIN,
            );

            return false;
        }

        return parent::uninstall();
    }

    public function getContent(): string
    {
        Tools::redirectAdmin($this->context->link->getAdminLink(DashboardController::TAB_CLASS_NAME));

        return '';
    }

    public function getOrderShippingCost($params, $shipping_cost): false
    {
        return false;
    }

    public function getOrderShippingCostExternal($params): false
    {
        return false;
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTabs(): array
    {
        $names = [
            DashboardController::TAB_CLASS_NAME => $this->translatedTabNames('QRK Shipping'),
            PreferencesController::TAB_CLASS_NAME => $this->translatedTabNames('Preferences'),
            HelpController::TAB_CLASS_NAME => $this->translatedTabNames('Help'),
            DiagnosticsController::TAB_CLASS_NAME => $this->translatedTabNames('Diagnostics'),
        ];

        return [
            [
                'route_name' => 'admin_qrkshipping_dashboard',
                'class_name' => DashboardController::TAB_CLASS_NAME,
                'visible' => true,
                'name' => $names[DashboardController::TAB_CLASS_NAME],
                'icon' => 'local_shipping',
                'parent_class_name' => 'IMPROVE',
            ],
            [
                'route_name' => 'admin_qrkshipping_preferences',
                'class_name' => PreferencesController::TAB_CLASS_NAME,
                'visible' => true,
                'name' => $names[PreferencesController::TAB_CLASS_NAME],
                'icon' => 'tune',
                'parent_class_name' => DashboardController::TAB_CLASS_NAME,
            ],
            [
                'route_name' => 'admin_qrkshipping_help',
                'class_name' => HelpController::TAB_CLASS_NAME,
                'visible' => true,
                'name' => $names[HelpController::TAB_CLASS_NAME],
                'icon' => 'help_outline',
                'parent_class_name' => DashboardController::TAB_CLASS_NAME,
            ],
            [
                'route_name' => 'admin_qrkshipping_diagnostics',
                'class_name' => DiagnosticsController::TAB_CLASS_NAME,
                'visible' => true,
                'name' => $names[DiagnosticsController::TAB_CLASS_NAME],
                'icon' => 'fact_check',
                'parent_class_name' => DashboardController::TAB_CLASS_NAME,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function translatedTabNames(string $message): array
    {
        $names = [];
        foreach (Language::getLanguages(true) as $language) {
            $locale = (string) ($language['locale'] ?? 'en-US');
            $names[$locale] = $this->trans($message, [], self::ADMIN_DOMAIN, $locale);
        }

        return $names;
    }

    private function requirementFailureMessage(RuntimeRequirementFailure $failure): string
    {
        $parameters = [];
        foreach ($failure->parameters() as $key => $value) {
            $parameters['%' . $key . '%'] = (string) $value;
        }

        $message = match ($failure->code()) {
            'php_version' => 'PHP %current% is installed; QRK Shipping requires PHP 8.2.32 or newer.',
            'prestashop_version' => 'PrestaShop %current% is outside the supported range: '
                . '9.1.4 or newer and lower than 10.0.',
            'extension_missing' => 'The required PHP extension "%extension%" is missing.',
            'symfony_missing' => 'The Symfony runtime supplied by PrestaShop is unavailable.',
            'symfony_version' => 'Symfony %current% is installed; QRK Shipping requires the '
                . 'PrestaShop-supplied Symfony 6.4 runtime.',
            'mariadb_version' => 'MariaDB %current% is installed; QRK Shipping requires MariaDB %required% or newer.',
            'mysql_version' => 'MySQL %current% is installed; QRK Shipping requires MySQL %required% or newer.',
            'database_engine' => 'The database default engine is %current%; InnoDB is required.',
            'database_utf8mb4_unsupported' => 'The database server does not provide a supported utf8mb4 collation.',
            'database_version_unknown' => 'The database server version could not be identified safely.',
            default => 'The database runtime could not be verified safely.',
        };

        return $this->trans($message, $parameters, self::ERROR_DOMAIN);
    }
}
