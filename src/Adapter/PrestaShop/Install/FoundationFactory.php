<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Install;

use Qrk\Commerce\Shipping\Adapter\PrestaShop\Persistence\PrestaShopDatabaseConnection;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Persistence\PrestaShopTransactionManager;
use Qrk\Commerce\Shipping\Application\Lifecycle\LifecyclePolicyService;
use Qrk\Commerce\Shipping\Application\Settings\SettingValueCodec;
use Qrk\Commerce\Shipping\Application\Settings\SettingsCatalog;
use Qrk\Commerce\Shipping\Application\Settings\SettingsService;
use Qrk\Commerce\Shipping\Infrastructure\Clock\SystemClock;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Repository\DbAuditEventRepository;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Repository\DbSecretRepository;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Repository\DbSettingRepository;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Repository\ScopePersistenceMapper;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\MigrationRecorder;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\NullSchemaStepObserver;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaCatalog;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaInspector;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaManager;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaPlanner;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaStepObserver;

final class FoundationFactory
{
    public static function installer(?SchemaStepObserver $observer = null): FoundationInstaller
    {
        $services = self::services($observer);

        return new FoundationInstaller(
            $services['schema_manager'],
            $services['audit_repository'],
            $services['clock'],
        );
    }

    public static function uninstaller(): FoundationUninstaller
    {
        $services = self::services();

        return new FoundationUninstaller(
            $services['connection'],
            $services['secret_repository'],
            $services['audit_repository'],
            $services['clock'],
            $services['schema_catalog'],
            self::databasePrefix(),
        );
    }

    public static function lifecyclePolicy(): LifecyclePolicyService
    {
        return self::services()['lifecycle_policy'];
    }

    /**
     * @return array{
     *   connection: PrestaShopDatabaseConnection,
     *   clock: SystemClock,
     *   schema_catalog: SchemaCatalog,
     *   schema_manager: SchemaManager,
     *   audit_repository: DbAuditEventRepository,
     *   secret_repository: DbSecretRepository,
     *   lifecycle_policy: LifecyclePolicyService
     * }
     */
    private static function services(?SchemaStepObserver $observer = null): array
    {
        $prefix = self::databasePrefix();
        $connection = new PrestaShopDatabaseConnection();
        $clock = new SystemClock();
        $scopeMapper = new ScopePersistenceMapper();
        $catalog = new SchemaCatalog();
        $inspector = new SchemaInspector($connection, $prefix);
        $auditRepository = new DbAuditEventRepository($connection, $scopeMapper, $prefix);
        $secretRepository = new DbSecretRepository($connection, $scopeMapper, $clock, $prefix);
        $settings = new SettingsService(
            new SettingsCatalog(),
            new SettingValueCodec(),
            new DbSettingRepository($connection, $scopeMapper, $clock, $prefix),
            $auditRepository,
            new PrestaShopTransactionManager($connection),
            $clock,
        );

        return [
            'connection' => $connection,
            'clock' => $clock,
            'schema_catalog' => $catalog,
            'schema_manager' => new SchemaManager(
                $connection,
                $catalog,
                $inspector,
                new SchemaPlanner(),
                new MigrationRecorder($connection, $prefix),
                $clock,
                $prefix,
                $observer ?? new NullSchemaStepObserver(),
            ),
            'audit_repository' => $auditRepository,
            'secret_repository' => $secretRepository,
            'lifecycle_policy' => new LifecyclePolicyService($settings),
        ];
    }

    private static function databasePrefix(): string
    {
        if (!defined('_DB_PREFIX_')) {
            throw new \RuntimeException('PrestaShop database prefix is unavailable.');
        }

        return (string) constant('_DB_PREFIX_');
    }

    private function __construct()
    {
    }
}
