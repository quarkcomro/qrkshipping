<?php

declare(strict_types=1);

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
use Qrk\Commerce\Shipping\Tests\Support\QueuedDatabaseConnection;
use Qrk\Commerce\Shipping\Tests\Support\ThrowingSchemaStepObserver;

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
