<?php

declare(strict_types=1);

use Qrk\Commerce\Shipping\Adapter\PrestaShop\Install\FoundationUninstaller;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Lifecycle\LifecycleOperationDetector;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Persistence\PrestaShopDatabaseConnection;
use Qrk\Commerce\Shipping\Application\Lifecycle\LifecycleOperation;
use Qrk\Commerce\Shipping\Application\Lifecycle\LifecyclePolicy;
use Qrk\Commerce\Shipping\Application\Lifecycle\LifecyclePolicyService;
use Qrk\Commerce\Shipping\Application\Security\SecretStoreService;
use Qrk\Commerce\Shipping\Application\Settings\SettingValueCodec;
use Qrk\Commerce\Shipping\Application\Settings\SettingsCatalog;
use Qrk\Commerce\Shipping\Application\Settings\SettingsService;
use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;
use Qrk\Commerce\Shipping\Domain\Money\DecimalAmount;
use Qrk\Commerce\Shipping\Domain\Money\RoundingMode;
use Qrk\Commerce\Shipping\Domain\Security\MasterKey;
use Qrk\Commerce\Shipping\Domain\Security\SecretLocator;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\MigrationRecorder;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaCatalog;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaInstallationException;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaInspector;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaManager;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaPlanner;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\Utf8mb4CollationResolver;
use Qrk\Commerce\Shipping\Infrastructure\Security\OpenSslAesGcmSecretCipher;
use Qrk\Commerce\Shipping\Tests\Support\FailureInjectingDatabaseConnection;
use Qrk\Commerce\Shipping\Tests\Support\FrozenClock;
use Qrk\Commerce\Shipping\Tests\Support\InMemoryAuditEventRepository;
use Qrk\Commerce\Shipping\Tests\Support\InMemorySecretRepository;
use Qrk\Commerce\Shipping\Tests\Support\InMemorySettingRepository;
use Qrk\Commerce\Shipping\Tests\Support\InMemoryTransactionManager;
use Qrk\Commerce\Shipping\Tests\Support\MutableMasterKeyProvider;
use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;
use Qrk\Commerce\Shipping\Tests\Support\QueuedDatabaseConnection;
use Qrk\Commerce\Shipping\Tests\Support\ThrowingSchemaStepObserver;

if (!class_exists('Db')) {
    class Db
    {
        private static ?self $instance = null;

        /** @var list<array{sql: string, array: bool, use_cache: bool}> */
        public array $executeSCalls = [];

        /** @var list<array<string, mixed>>|false */
        public array|false $executeSResult = [];

        public static function getInstance(bool $useMaster = true): self
        {
            return self::$instance ??= new self();
        }

        public static function setInstanceForTesting(self $instance): void
        {
            self::$instance = $instance;
        }

        public function execute(string $sql): bool
        {
            return true;
        }

        /** @return list<array<string, mixed>>|false */
        public function executeS(string $sql, bool $array = true, bool $useCache = true): array|false
        {
            $this->executeSCalls[] = ['sql' => $sql, 'array' => $array, 'use_cache' => $useCache];

            return $this->executeSResult;
        }

        public function escape(string $value, bool $htmlOk = false, bool $bqSql = false): string
        {
            return str_replace("'", "''", $value);
        }

        public function Insert_ID(): int
        {
            return 0;
        }

        public function Affected_Rows(): int
        {
            return 0;
        }

        public function getMsgError(): string
        {
            return '';
        }
    }
}

if (!function_exists('mb_strlen')) {
    function mb_strlen(string $value, ?string $encoding = null): int
    {
        $count = preg_match_all('/./us', $value, $matches);

        return $count === false ? strlen($value) : $count;
    }
}

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'Qrk\\Commerce\\Shipping\\Tests\\' => $root . '/tests/',
        'Qrk\\Commerce\\Shipping\\' => $root . '/src/',
    ];
    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $path = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$databaseProbe = new Db();
$databaseProbe->executeSResult = [['value' => 'fresh']];
Db::setInstanceForTesting($databaseProbe);
$databaseRows = (new PrestaShopDatabaseConnection())->fetchAll('SELECT authoritative metadata');
$assert($databaseRows === [['value' => 'fresh']], 'Authoritative database read returned unexpected data.');
$assert(
    $databaseProbe->executeSCalls === [[
        'sql' => 'SELECT authoritative metadata',
        'array' => true,
        'use_cache' => false,
    ]],
    'Authoritative database read did not bypass the PrestaShop SQL result cache.',
);

$assert((string) DecimalAmount::fromString('001.230000') === '1.23', 'Decimal normalization failed.');
$assert(
    (string) DecimalAmount::fromString('1.225')->round(2, RoundingMode::HALF_EVEN) === '1.22',
    'Half-even rounding failed.',
);
$assert(
    (string) DecimalAmount::fromString('-2.5')->add(DecimalAmount::fromString('3.75')) === '1.25',
    'Exact addition failed.',
);

$assert(
    (new Utf8mb4CollationResolver(new QueuedDatabaseConnection([
        ['collation' => 'utf8mb3_general_ci'],
        ['collation' => 'utf8mb4_unicode_ci'],
    ])))->resolve() === 'utf8mb4_unicode_ci',
    'Legacy database default did not fall back to portable utf8mb4.',
);
$assert(
    (new Utf8mb4CollationResolver(new QueuedDatabaseConnection([
        ['collation' => 'utf8mb3_general_ci'],
        null,
        null,
    ])))->resolve() === null,
    'Missing portable utf8mb4 support did not fail closed.',
);

$clock = new FrozenClock(new DateTimeImmutable('2026-07-11T12:00:00+00:00', new DateTimeZone('UTC')));
$settingsRepository = new InMemorySettingRepository();
$audit = new InMemoryAuditEventRepository();
$settings = new SettingsService(
    new SettingsCatalog(),
    new SettingValueCodec(),
    $settingsRepository,
    $audit,
    new InMemoryTransactionManager(),
    $clock,
);
$shop = SettingScope::shop(9, 3);
$settings->set(SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL, SettingScope::all(), 'detailed', AuditActor::employee(1));
$assert(
    $settings->resolve(SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL, $shop)->value() === 'detailed',
    'Setting inheritance failed.',
);
$settings->set(SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL, $shop, 'standard', AuditActor::employee(1));
$assert(
    $settings->resolve(SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL, $shop)->value() === 'standard',
    'Shop override failed.',
);
$settings->removeOverride(SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL, $shop, AuditActor::employee(1));
$assert(
    $settings->resolve(SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL, $shop)->value() === 'detailed',
    'Override removal failed.',
);

$uninstallOnlyPolicy = new LifecyclePolicy(true, false);
$assert(
    $uninstallOnlyPolicy->purgeFor(LifecycleOperation::UNINSTALL),
    'Uninstall-specific destructive policy was not selected.',
);
$assert(
    !$uninstallOnlyPolicy->purgeFor(LifecycleOperation::RESET),
    'Reset incorrectly consumed the uninstall destructive policy.',
);

$lifecycle = new LifecyclePolicyService($settings);
$assert(!$lifecycle->current()->purgeOnUninstall(), 'Lifecycle uninstall default is destructive.');
$assert(!$lifecycle->current()->resetToDefaults(), 'Lifecycle reset default is destructive.');
$lifecycle->save(true, true, AuditActor::employee(1));
$assert($lifecycle->current()->purgeOnUninstall(), 'Lifecycle uninstall policy was not saved.');
$assert($lifecycle->current()->resetToDefaults(), 'Lifecycle reset policy was not saved.');

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '9.1.4');
}
require_once $root . '/upgrade/upgrade-0.1.2.php';
$upgradeProbe = new class {
    /** @var list<string> */
    public array $hooks = [];

    public function registerHook(string $hookName): bool
    {
        $this->hooks[] = $hookName;

        return true;
    }
};
$assert(upgrade_module_0_1_2($upgradeProbe), 'The 0.1.2 upgrade entrypoint failed.');
$assert($upgradeProbe->hooks === ['actionBeforeResetModule'], 'The reset lifecycle hook was not upgraded.');

$consoleResetDetector = new LifecycleOperationDetector(null, [
    'bin/console',
    'prestashop:module',
    'reset',
    'qrkshipping',
    '--env=prod',
]);
$assert($consoleResetDetector->isResetFor('qrkshipping'), 'The official console reset was not detected.');
$assert(!$consoleResetDetector->isResetFor('anothermodule'), 'A reset for another module was misclassified.');

$purgeConnection = new class implements DatabaseConnectionPort {
    /** @var list<string> */
    public array $executed = [];
    private int $metadataReads = 0;

    public function execute(string $sql): void
    {
        $this->executed[] = $sql;
    }

    public function fetchOne(string $sql): ?array
    {
        return null;
    }

    public function fetchAll(string $sql): array
    {
        ++$this->metadataReads;

        return $this->metadataReads === 1
            ? [['TABLE_NAME' => 'smoke_qrkship_future_extension']]
            : [];
    }

    public function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    public function lastInsertId(): int
    {
        return 0;
    }

    public function affectedRows(): int
    {
        return 0;
    }
};
$purgeCatalog = new SchemaCatalog();
$purgeUninstaller = new FoundationUninstaller(
    $purgeConnection,
    new InMemorySecretRepository(),
    new InMemoryAuditEventRepository(),
    $clock,
    $purgeCatalog,
    'smoke_',
);
$assert($purgeUninstaller->purgeAllData() === 0, 'Namespace purge returned an unexpected secret count.');
$assert(count($purgeConnection->executed) === 1, 'Namespace purge did not use one atomic DROP statement.');
$assert(
    str_contains($purgeConnection->executed[0], '`smoke_qrkship_future_extension`'),
    'Namespace purge omitted a future QRK Shipping table.',
);

$secrets = new InMemorySecretRepository();
$keys = new MutableMasterKeyProvider(new MasterKey('smoke-key', str_repeat('k', 32)));
$secretService = new SecretStoreService(
    $secrets,
    $keys,
    new OpenSslAesGcmSecretCipher(),
    $audit,
    new InMemoryTransactionManager(),
    $clock,
);
$locator = new SecretLocator(1, $shop, 'api_token');
$assert($secretService->write($locator, 'smoke-secret', AuditActor::employee(1)), 'Secret write failed.');
$assert($secretService->read($locator) === 'smoke-secret', 'Secret round trip failed.');
$assert(!$secretService->write($locator, '', AuditActor::employee(1)), 'Empty secret edit did not preserve.');
$assert($secretService->read($locator) === 'smoke-secret', 'Preserved secret changed.');

for ($failureStep = 1; $failureStep <= 5; ++$failureStep) {
    $connection = new FailureInjectingDatabaseConnection();
    $catalog = new SchemaCatalog();
    $prefix = 'smoke_';
    $manager = new SchemaManager(
        $connection,
        $catalog,
        new SchemaInspector($connection, $prefix),
        new SchemaPlanner(),
        new MigrationRecorder($connection, $prefix),
        $clock,
        $prefix,
        new ThrowingSchemaStepObserver($failureStep),
    );

    try {
        $manager->install();
        throw new RuntimeException('Injected schema failure did not fire.');
    } catch (SchemaInstallationException $exception) {
        $assert($exception->rollbackFailures() === [], 'Schema rollback reported a failure.');
        $assert($connection->tableNames() === [], 'Schema rollback left current-run tables behind.');
    }
}

foreach ($audit->events as $event) {
    $assert(!str_contains($event->metadataJson(), 'smoke-secret'), 'Audit leaked secret plaintext.');
}

fwrite(STDOUT, sprintf("Local smoke: %d assertions passed.\n", $checks));
