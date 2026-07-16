<?php

declare(strict_types=1);

use PrestaShop\PrestaShop\Core\Context\ShopContext;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use PrestaShopBundle\Security\Attribute\DemoRestricted;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Install\FoundationFactory;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Multistore\ShopContextResolver;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Multistore\ShopTopology;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Persistence\PrestaShopDatabaseConnection;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Security\PrestaShopMasterKeyProvider;
use Qrk\Commerce\Shipping\Controller\Admin\DashboardController;
use Qrk\Commerce\Shipping\Controller\Admin\DiagnosticsController;
use Qrk\Commerce\Shipping\Controller\Admin\HelpController;
use Qrk\Commerce\Shipping\Application\Lifecycle\LifecyclePolicyAccess;
use Qrk\Commerce\Shipping\Application\Settings\SettingsCatalog;
use Qrk\Commerce\Shipping\Controller\Admin\PreferencesController;
use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScopeType;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\MigrationRecorder;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaCatalog;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaInspector;
use Qrk\Commerce\Shipping\Infrastructure\Security\OpenSslAesGcmSecretCipher;
use Qrk\Commerce\Shipping\ModuleMetadata;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

$stage = $argv[1] ?? '';
$allowedStages = [
    'installed',
    'disabled',
    'enabled',
    'seeded',
    'reset_retained',
    'uninstalled',
    'reinstalled',
    'seed_reset_defaults',
    'reset_defaults',
    'seed_uninstall_purge',
    'uninstalled_purged',
    'reinstalled_after_purge',
];
if (!in_array($stage, $allowedStages, true)) {
    fwrite(STDERR, 'Usage: php scripts/prestashop-runtime-check.php <' . implode('|', $allowedStages) . ">\n");
    exit(2);
}

$prestaShopRoot = getenv('QRK_PS_ROOT');
if (!is_string($prestaShopRoot) || $prestaShopRoot === '') {
    fwrite(STDERR, "QRK_PS_ROOT is required.\n");
    exit(2);
}
$prestaShopRoot = realpath($prestaShopRoot);
if ($prestaShopRoot === false || !is_file($prestaShopRoot . '/config/config.inc.php')) {
    fwrite(STDERR, "QRK_PS_ROOT does not point to an installed PrestaShop tree.\n");
    exit(2);
}

$_SERVER['HTTP_HOST'] ??= 'localhost';
$_SERVER['SERVER_NAME'] ??= 'localhost';
$_SERVER['REQUEST_URI'] ??= '/';
$_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
$_SERVER['REQUEST_METHOD'] ??= 'GET';

require_once $prestaShopRoot . '/config/config.inc.php';
require_once $prestaShopRoot . '/modules/qrkshipping/vendor/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$connection = new PrestaShopDatabaseConnection();
$prefix = defined('_DB_PREFIX_') ? (string) constant('_DB_PREFIX_') : '';
$assert($prefix !== '', 'PrestaShop database prefix is unavailable.');
$assert(defined('_PS_VERSION_'), 'PrestaShop version constant is unavailable.');
$assert(
    version_compare((string) constant('_PS_VERSION_'), ModuleMetadata::MIN_PRESTASHOP_VERSION, '>='),
    'The runtime PrestaShop version is below the approved minimum.',
);
$assert(
    version_compare((string) constant('_PS_VERSION_'), ModuleMetadata::MAX_PRESTASHOP_VERSION_EXCLUSIVE, '<'),
    'The runtime PrestaShop version is outside the approved major line.',
);
$assert(PHP_VERSION_ID >= ModuleMetadata::MIN_PHP_VERSION_ID, 'The runtime PHP version is below 8.2.32.');

if (getenv('QRK_EXPECT_LEGACY_DB_DEFAULT') === '1') {
    $databaseDefaults = $connection->fetchOne(
        'SELECT @@character_set_database AS `charset`, @@collation_database AS `collation`',
    );
    $databaseCharset = strtolower((string) ($databaseDefaults['charset'] ?? ''));
    $databaseCollation = strtolower((string) ($databaseDefaults['collation'] ?? ''));
    $assert(
        in_array($databaseCharset, ['utf8', 'utf8mb3'], true),
        'The runtime database does not use the requested legacy utf8/utf8mb3 default.',
    );
    $assert(
        str_starts_with($databaseCollation, 'utf8_') || str_starts_with($databaseCollation, 'utf8mb3_'),
        'The runtime database does not use the requested legacy utf8/utf8mb3 collation.',
    );
}

$moduleRow = $connection->fetchOne(sprintf(
    'SELECT `id_module` FROM `%smodule` WHERE `name` = %s',
    $prefix,
    $connection->quote(ModuleMetadata::NAME),
));
$installed = $moduleRow !== null;
$expectedInstalled = !in_array($stage, ['uninstalled', 'uninstalled_purged'], true);
$assert($installed === $expectedInstalled, 'The module installation state does not match the runtime stage.');

$expectedTables = array_map(
    static fn (string $suffix): string => $prefix . $suffix,
    (new SchemaCatalog())->suffixes(),
);
sort($expectedTables);
$quotedExpectedTables = implode(', ', array_map(
    static fn (string $tableName): string => $connection->quote($tableName),
    $expectedTables,
));
$tableRows = $connection->fetchAll(
    'SELECT `TABLE_NAME` FROM `INFORMATION_SCHEMA`.`TABLES` '
    . 'WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` IN (' . $quotedExpectedTables . ') '
    . 'ORDER BY `TABLE_NAME` ASC',
);
$actualTables = array_map(
    static fn (array $row): string => (string) ($row['TABLE_NAME'] ?? ''),
    $tableRows,
);
$expectsFoundationTables = $stage !== 'uninstalled_purged';
$assert(
    $actualTables === ($expectsFoundationTables ? $expectedTables : []),
    $expectsFoundationTables
        ? 'The runtime foundation does not contain exactly the five approved tables.'
        : 'Destructive uninstall left one or more approved QRK Shipping tables behind.',
);

$namespacePrefix = $prefix . 'qrkship_';
$namespaceRows = $connection->fetchAll(sprintf(
    'SELECT `TABLE_NAME` FROM `INFORMATION_SCHEMA`.`TABLES` '
    . 'WHERE `TABLE_SCHEMA` = DATABASE() AND LEFT(`TABLE_NAME`, %d) = %s '
    . 'ORDER BY `TABLE_NAME` ASC',
    strlen($namespacePrefix),
    $connection->quote($namespacePrefix),
));
$namespaceTables = array_map(
    static fn (array $row): string => (string) ($row['TABLE_NAME'] ?? ''),
    $namespaceRows,
);
if ($stage === 'uninstalled_purged') {
    $assert($namespaceTables === [], 'Destructive uninstall left a table in the QRK Shipping namespace.');
}

$catalog = new SchemaCatalog();
if ($expectsFoundationTables) {
    $inspector = new SchemaInspector($connection, $prefix);
    $inspector->assertCompatible($catalog);
    (new MigrationRecorder($connection, $prefix))->assertCurrent($catalog);
}

$strictMode = $connection->fetchOne('SELECT @@SESSION.sql_mode AS `mode`');
$assert(
    str_contains(strtoupper((string) ($strictMode['mode'] ?? '')), 'STRICT'),
    'The runtime database session is not using a strict SQL mode.',
);

$carrierRow = $connection->fetchOne(sprintf(
    'SELECT COUNT(*) AS `count` FROM `%scarrier` WHERE `external_module_name` = %s',
    $prefix,
    $connection->quote(ModuleMetadata::NAME),
));
$assert((int) ($carrierRow['count'] ?? -1) === 0, 'QRK Shipping created a carrier in the inert increment.');

$expectedTabClasses = [
    DashboardController::TAB_CLASS_NAME,
    PreferencesController::TAB_CLASS_NAME,
    HelpController::TAB_CLASS_NAME,
    DiagnosticsController::TAB_CLASS_NAME,
];
$tabRows = $connection->fetchAll(sprintf(
    'SELECT `class_name`, `route_name`, `active` FROM `%stab` '
    . 'WHERE `module` = %s ORDER BY `class_name` ASC',
    $prefix,
    $connection->quote(ModuleMetadata::NAME),
));

if (in_array($stage, ['uninstalled', 'uninstalled_purged'], true)) {
    $assert($tabRows === [], 'Module tabs were not removed during uninstall.');
} else {
    $actualTabClasses = array_map(
        static fn (array $row): string => (string) ($row['class_name'] ?? ''),
        $tabRows,
    );
    sort($actualTabClasses);
    $sortedExpectedTabs = $expectedTabClasses;
    sort($sortedExpectedTabs);
    $assert($actualTabClasses === $sortedExpectedTabs, 'The four approved Back Office tabs are not registered.');

    $expectedActive = $stage === 'disabled' ? 0 : 1;
    foreach ($tabRows as $row) {
        $assert((int) ($row['active'] ?? -1) === $expectedActive, 'A Back Office tab has the wrong enabled state.');
        $assert((string) ($row['route_name'] ?? '') !== '', 'A Back Office tab has no Symfony route.');
    }

    foreach ($expectedTabClasses as $tabClass) {
        foreach (['CREATE', 'READ', 'UPDATE', 'DELETE'] as $action) {
            $slug = 'ROLE_MOD_TAB_' . strtoupper($tabClass) . '_' . $action;
            $role = $connection->fetchOne(sprintf(
                'SELECT `slug` FROM `%sauthorization_role` WHERE `slug` = %s',
                $prefix,
                $connection->quote($slug),
            ));
            $assert($role !== null, 'A required Back Office authorization role is missing.');
        }
    }
}

if ($installed) {
    $module = Module::getInstanceByName(ModuleMetadata::NAME);
    if (!$module instanceof QrkShipping) {
        throw new RuntimeException('PrestaShop did not load the QrkShipping module class.');
    }
    ++$assertions;
    $assert($module->version === ModuleMetadata::VERSION, 'Loaded module version does not match metadata.');
    $expectedActive = $stage === 'disabled' ? 0 : 1;
    $assert((int) $module->active === $expectedActive, 'The module enabled state does not match the stage.');

    $resetHookRow = $connection->fetchOne(sprintf(
        'SELECT COUNT(*) AS `count` FROM `%shook_module` hm '
        . 'INNER JOIN `%shook` h ON h.`id_hook` = hm.`id_hook` '
        . 'WHERE hm.`id_module` = %d AND h.`name` = %s',
        $prefix,
        $prefix,
        (int) $module->id,
        $connection->quote('actionBeforeResetModule'),
    ));
    $assert(
        (int) ($resetHookRow['count'] ?? 0) >= 1,
        'The actionBeforeResetModule lifecycle hook is not registered.',
    );

    $beforeCounts = [];
    foreach ($expectedTables as $tableName) {
        $row = $connection->fetchOne(sprintf('SELECT COUNT(*) AS `count` FROM `%s`', $tableName));
        $beforeCounts[$tableName] = (int) ($row['count'] ?? -1);
    }
    $assert($module->getOrderShippingCost(null, 0.0) === false, 'Local shipping-cost method is not inert.');
    $assert($module->getOrderShippingCostExternal(null) === false, 'External shipping-cost method is not inert.');
    foreach ($expectedTables as $tableName) {
        $row = $connection->fetchOne(sprintf('SELECT COUNT(*) AS `count` FROM `%s`', $tableName));
        $assert(
            (int) ($row['count'] ?? -2) === $beforeCounts[$tableName],
            'A shipping-cost method wrote to the database.',
        );
    }

    $cipher = new OpenSslAesGcmSecretCipher();
    $masterKey = (new PrestaShopMasterKeyProvider())->current();
    $envelope = $cipher->encrypt('runtime-probe', 'qrkshipping-runtime-aad', $masterKey);
    $assert(
        $cipher->decrypt($envelope, 'qrkshipping-runtime-aad', $masterKey) === 'runtime-probe',
        'Authenticated encryption failed with the real PrestaShop key material.',
    );

    /** @var list<array{class-string, non-empty-string, bool}> $controllerMethods */
    $controllerMethods = [
        [DashboardController::class, 'indexAction', false],
        [PreferencesController::class, 'indexAction', false],
        [PreferencesController::class, 'updateAction', true],
        [HelpController::class, 'indexAction', false],
        [DiagnosticsController::class, 'indexAction', false],
    ];
    foreach ($controllerMethods as [$controllerClass, $methodName, $mustBeDemoRestricted]) {
        $method = new ReflectionMethod($controllerClass, $methodName);
        $assert(
            count($method->getAttributes(AdminSecurity::class)) === 1,
            'A Back Office controller action is missing AdminSecurity.',
        );
        $assert(
            (count($method->getAttributes(DemoRestricted::class)) === 1) === $mustBeDemoRestricted,
            'The preferences mutation demo-mode restriction is incorrect.',
        );
    }

    $constructor = new ReflectionMethod(PreferencesController::class, '__construct');
    $csrfDependencyFound = false;
    foreach ($constructor->getParameters() as $parameter) {
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType && $type->getName() === CsrfTokenManagerInterface::class) {
            $csrfDependencyFound = true;
        }
    }
    $assert($csrfDependencyFound, 'PreferencesController is not wired to the Symfony CSRF token manager.');

    /** @var list<array{ShopConstraint, int, int, string, list<int>, SettingScope}> $shopContexts */
    $shopContexts = [
        [ShopConstraint::allShops(), 0, 0, '', [1, 2], SettingScope::all()],
        [ShopConstraint::shopGroup(7), 0, 7, '', [11, 12], SettingScope::shopGroup(7)],
        [ShopConstraint::shop(11), 11, 7, 'Runtime shop', [11], SettingScope::shop(11, 7)],
    ];
    foreach ($shopContexts as [$constraint, $shopId, $groupId, $name, $associatedShopIds, $expectedScope]) {
        $shopContext = new ShopContext(
            $constraint,
            $shopId,
            $name,
            $groupId,
            0,
            'classic',
            '',
            '/',
            '',
            'localhost',
            'localhost',
            true,
            false,
            $associatedShopIds,
            true,
            true,
            false,
            false,
            false,
        );
        $resolved = (new ShopContextResolver($shopContext))->current();
        $assert($resolved->scope()->equals($expectedScope), 'PrestaShop multistore context mapping failed.');
    }

    $configuredShopCount = (new ShopTopology())->configuredShopCount();
    $assert($configuredShopCount === 1, 'The runtime fixture does not contain exactly one configured shop.');
    $singleShopPolicyAccess = LifecyclePolicyAccess::evaluate(
        SettingScopeType::SHOP,
        $configuredShopCount,
        true,
    );
    $assert(
        $singleShopPolicyAccess->canEdit() && $singleShopPolicyAccess->usesSingleShopFallback(),
        'The single-shop global lifecycle-policy fallback is unavailable.',
    );
    $assert(
        !LifecyclePolicyAccess::evaluate(SettingScopeType::SHOP, 2, true)->canEdit(),
        'A shop context can edit global lifecycle policy when two shops are configured.',
    );
}

$seedRetainedData = static function () use ($connection, $prefix): void {
    $now = gmdate('Y-m-d H:i:s');
    $connection->execute(sprintf(
        'DELETE FROM `%sqrkship_setting` WHERE `setting_key` = %s',
        $prefix,
        $connection->quote('runtime.retained_marker'),
    ));
    $connection->execute(sprintf(
        'INSERT INTO `%sqrkship_setting` '
        . '(`setting_key`, `value_type`, `scope_type`, `id_shop_group`, `id_shop`, '
        . '`value_text`, `is_empty`, `created_at`, `updated_at`) '
        . 'VALUES (%s, %s, %s, 0, 0, %s, 0, %s, %s)',
        $prefix,
        $connection->quote('runtime.retained_marker'),
        $connection->quote('string'),
        $connection->quote('all'),
        $connection->quote('retained'),
        $connection->quote($now),
        $connection->quote($now),
    ));
    $connection->execute(sprintf('DELETE FROM `%sqrkship_secret`', $prefix));
    $connection->execute(sprintf(
        'INSERT INTO `%sqrkship_secret` '
        . '(`provider_account_id`, `secret_key`, `scope_type`, `id_shop_group`, `id_shop`, '
        . '`cipher_version`, `key_id`, `nonce_b64`, `ciphertext_b64`, `auth_tag_b64`, '
        . '`created_at`, `updated_at`) '
        . 'VALUES (999, %s, %s, 0, 0, 1, %s, %s, %s, %s, %s, %s)',
        $prefix,
        $connection->quote('runtime_probe'),
        $connection->quote('all'),
        $connection->quote('runtime-key'),
        $connection->quote(base64_encode(random_bytes(12))),
        $connection->quote(base64_encode(random_bytes(32))),
        $connection->quote(base64_encode(random_bytes(16))),
        $connection->quote($now),
        $connection->quote($now),
    ));
};

$assertSecretRowsRemoved = static function () use ($connection, $prefix, $assert): void {
    $secretCount = $connection->fetchOne(sprintf(
        'SELECT COUNT(*) AS `count` FROM `%sqrkship_secret`',
        $prefix,
    ));
    $assert((int) ($secretCount['count'] ?? -1) === 0, 'Encrypted secret rows survived a lifecycle operation.');
};

$assertRetainedMarker = static function (bool $expected) use ($connection, $prefix, $assert): void {
    $settingCount = $connection->fetchOne(sprintf(
        'SELECT COUNT(*) AS `count` FROM `%sqrkship_setting` '
        . 'WHERE `setting_key` = %s AND `value_text` = %s',
        $prefix,
        $connection->quote('runtime.retained_marker'),
        $connection->quote('retained'),
    ));
    $assert(
        ((int) ($settingCount['count'] ?? 0) === 1) === $expected,
        $expected
            ? 'Non-secret foundation data was not retained.'
            : 'Reset-to-defaults retained a non-default marker.',
    );
};

$assertLifecycleDefaults = static function () use ($assert): void {
    $policy = FoundationFactory::lifecyclePolicy()->current();
    $assert(!$policy->purgeOnUninstall(), 'Purge-on-uninstall did not return to its default value.');
    $assert(!$policy->resetToDefaults(), 'Reset-to-defaults did not return to its default value.');
};

$assertLifecycleAudit = static function (string $eventCode, string $operation) use (
    $connection,
    $prefix,
    $assert,
): void {
    $row = $connection->fetchOne(sprintf(
        'SELECT `event_code`, `metadata_text` FROM `%sqrkship_audit_event` '
        . 'WHERE `event_code` = %s ORDER BY `id_audit_event` DESC',
        $prefix,
        $connection->quote($eventCode),
    ));
    $assert($row !== null, 'Expected lifecycle audit event is missing.');
    $metadata = json_decode((string) ($row['metadata_text'] ?? ''), true, flags: JSON_THROW_ON_ERROR);
    $assert(
        is_array($metadata) && ($metadata['lifecycle_operation'] ?? null) === $operation,
        'Lifecycle audit operation metadata is incorrect.',
    );
};

if ($stage === 'seeded') {
    $seedRetainedData();
}

if ($stage === 'reset_retained') {
    $assertSecretRowsRemoved();
    $assertRetainedMarker(true);
    $assertLifecycleDefaults();
    $assertLifecycleAudit('module.reset_secrets_removed', 'reset');
}

if (in_array($stage, ['uninstalled', 'reinstalled'], true)) {
    $assertSecretRowsRemoved();
    $assertRetainedMarker(true);
    if ($stage === 'uninstalled') {
        $assertLifecycleAudit('module.uninstalled_secrets_removed', 'uninstall');
    }
}

if ($stage === 'seed_reset_defaults') {
    FoundationFactory::lifecyclePolicy()->save(false, true, AuditActor::system());
    $seedRetainedData();
    $connection->execute(sprintf(
        'CREATE TABLE `%sqrkship_runtime_probe` (`id` INT NOT NULL PRIMARY KEY) '
        . 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        $prefix,
    ));
}

if ($stage === 'reset_defaults') {
    $assertSecretRowsRemoved();
    $assertRetainedMarker(false);
    $assertLifecycleDefaults();
    $probe = $connection->fetchOne(sprintf(
        'SELECT `TABLE_NAME` FROM `INFORMATION_SCHEMA`.`TABLES` '
        . 'WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = %s',
        $connection->quote($prefix . 'qrkship_runtime_probe'),
    ));
    $assert($probe === null, 'Reset-to-defaults left a table in the QRK Shipping namespace.');
}

if ($stage === 'seed_uninstall_purge') {
    FoundationFactory::lifecyclePolicy()->save(true, false, AuditActor::system());
    $seedRetainedData();
    $connection->execute(sprintf(
        'CREATE TABLE `%sqrkship_runtime_probe` (`id` INT NOT NULL PRIMARY KEY) '
        . 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        $prefix,
    ));
}

if ($stage === 'reinstalled_after_purge') {
    $assertSecretRowsRemoved();
    $assertRetainedMarker(false);
    $assertLifecycleDefaults();
}

$installationAuditStages = [
    'installed',
    'reinstalled',
    'reset_retained',
    'reset_defaults',
    'reinstalled_after_purge',
];
if (in_array($stage, $installationAuditStages, true)) {
    $auditRow = $connection->fetchOne(sprintf(
        'SELECT `metadata_text` FROM `%sqrkship_audit_event` '
        . 'WHERE `event_code` = %s ORDER BY `id_audit_event` DESC',
        $prefix,
        $connection->quote('module.foundation_installed'),
    ));
    $assert($auditRow !== null, 'Foundation installation audit event is missing.');
    $metadata = json_decode((string) ($auditRow['metadata_text'] ?? ''), true, flags: JSON_THROW_ON_ERROR);
    $expectedAction = match ($stage) {
        'installed', 'reset_defaults', 'reinstalled_after_purge' => 'create',
        default => 'adopt_existing',
    };
    $assert(
        is_array($metadata) && ($metadata['schema_action'] ?? null) === $expectedAction,
        'Foundation schema action audit does not match the lifecycle stage.',
    );
}

fwrite(STDOUT, sprintf(
    "PrestaShop runtime stage %s: %d assertions passed on PS %s, PHP %s.\n",
    $stage,
    $assertions,
    (string) constant('_PS_VERSION_'),
    PHP_VERSION,
));
