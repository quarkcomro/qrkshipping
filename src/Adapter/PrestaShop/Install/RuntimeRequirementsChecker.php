<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Install;

use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\Utf8mb4CollationResolver;
use Qrk\Commerce\Shipping\ModuleMetadata;
use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;
use Throwable;

final class RuntimeRequirementsChecker
{
    private readonly Utf8mb4CollationResolver $collationResolver;

    public function __construct(
        private readonly DatabaseConnectionPort $connection,
        ?Utf8mb4CollationResolver $collationResolver = null,
    ) {
        $this->collationResolver = $collationResolver ?? new Utf8mb4CollationResolver($connection);
    }

    /**
     * @return list<RuntimeRequirementFailure>
     */
    public function check(): array
    {
        $failures = [];

        if (PHP_VERSION_ID < ModuleMetadata::MIN_PHP_VERSION_ID) {
            $failures[] = new RuntimeRequirementFailure('php_version', [
                'current' => PHP_VERSION,
                'required' => '8.2.32',
            ]);
        }

        $prestaShopVersion = defined('_PS_VERSION_') ? (string) constant('_PS_VERSION_') : '';
        if (
            $prestaShopVersion === ''
            || version_compare($prestaShopVersion, ModuleMetadata::MIN_PRESTASHOP_VERSION, '<')
            || version_compare($prestaShopVersion, ModuleMetadata::MAX_PRESTASHOP_VERSION_EXCLUSIVE, '>=')
        ) {
            $failures[] = new RuntimeRequirementFailure('prestashop_version', [
                'current' => $prestaShopVersion === '' ? 'unknown' : $prestaShopVersion,
                'minimum' => ModuleMetadata::MIN_PRESTASHOP_VERSION,
                'maximum_exclusive' => ModuleMetadata::MAX_PRESTASHOP_VERSION_EXCLUSIVE,
            ]);
        }

        foreach (['json', 'mbstring', 'openssl'] as $extension) {
            if (!extension_loaded($extension)) {
                $failures[] = new RuntimeRequirementFailure('extension_missing', ['extension' => $extension]);
            }
        }

        if (!class_exists(\Symfony\Component\HttpKernel\Kernel::class)) {
            $failures[] = new RuntimeRequirementFailure('symfony_missing');
        } else {
            $versionId = \Symfony\Component\HttpKernel\Kernel::VERSION_ID;
            if ($versionId < 60400 || $versionId >= 60500) {
                $failures[] = new RuntimeRequirementFailure('symfony_version', [
                    'current' => \Symfony\Component\HttpKernel\Kernel::VERSION,
                    'required' => '6.4.x',
                ]);
            }
        }

        try {
            $this->checkDatabase($failures);
        } catch (Throwable) {
            $failures[] = new RuntimeRequirementFailure('database_probe_failed');
        }

        return $failures;
    }

    /**
     * @param list<RuntimeRequirementFailure> $failures
     */
    private function checkDatabase(array &$failures): void
    {
        $row = $this->connection->fetchOne(
            'SELECT VERSION() AS `version`, @@version_comment AS `version_comment`, '
            . '@@default_storage_engine AS `engine`',
        );

        if ($row === null) {
            $failures[] = new RuntimeRequirementFailure('database_probe_failed');

            return;
        }

        $rawVersion = (string) ($row['version'] ?? '');
        $comment = (string) ($row['version_comment'] ?? '');
        $isMariaDb = stripos($rawVersion . ' ' . $comment, 'mariadb') !== false;
        $version = $this->extractDatabaseVersion($rawVersion, $isMariaDb);

        if ($version === null) {
            $failures[] = new RuntimeRequirementFailure('database_version_unknown', [
                'reported' => $rawVersion,
            ]);
        } elseif ($isMariaDb && version_compare($version, ModuleMetadata::MIN_MARIADB_VERSION, '<')) {
            $failures[] = new RuntimeRequirementFailure('mariadb_version', [
                'current' => $version,
                'required' => ModuleMetadata::MIN_MARIADB_VERSION,
            ]);
        } elseif (!$isMariaDb && version_compare($version, ModuleMetadata::MIN_MYSQL_VERSION, '<')) {
            $failures[] = new RuntimeRequirementFailure('mysql_version', [
                'current' => $version,
                'required' => ModuleMetadata::MIN_MYSQL_VERSION,
            ]);
        }

        if (strtolower((string) ($row['engine'] ?? '')) !== 'innodb') {
            $failures[] = new RuntimeRequirementFailure('database_engine', [
                'current' => (string) ($row['engine'] ?? 'unknown'),
                'required' => 'InnoDB',
            ]);
        }

        if ($this->collationResolver->resolve() === null) {
            $failures[] = new RuntimeRequirementFailure('database_utf8mb4_unsupported');
        }
    }

    private function extractDatabaseVersion(string $rawVersion, bool $isMariaDb): ?string
    {
        if ($isMariaDb && preg_match('/(\d+\.\d+\.\d+)(?=-MariaDB)/i', $rawVersion, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/(\d+\.\d+\.\d+)/', $rawVersion, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
