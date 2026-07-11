<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Diagnostics;

use Qrk\Commerce\Shipping\Adapter\PrestaShop\Install\RuntimeRequirementFailure;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Install\RuntimeRequirementsChecker;
use Qrk\Commerce\Shipping\Domain\Security\SecretLocator;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\MigrationRecorder;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaCatalog;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaInspector;
use Qrk\Commerce\Shipping\Port\Security\MasterKeyProviderPort;
use Qrk\Commerce\Shipping\Port\Security\SecretCipherPort;
use Throwable;

final class FoundationDiagnosticsService
{
    public function __construct(
        private readonly RuntimeRequirementsChecker $runtimeRequirements,
        private readonly SchemaCatalog $schemaCatalog,
        private readonly SchemaInspector $schemaInspector,
        private readonly MigrationRecorder $migrationRecorder,
        private readonly MasterKeyProviderPort $masterKeyProvider,
        private readonly SecretCipherPort $secretCipher,
        private readonly string $moduleDirectory,
    ) {
    }

    /**
     * This method is intentionally read-only. The cryptographic probe remains entirely in memory.
     */
    public function run(): DiagnosticsReport
    {
        return new DiagnosticsReport([
            $this->runtimeCheck(),
            $this->autoloadCheck(),
            $this->routingCheck(),
            $this->translationCheck(),
            $this->schemaCheck(),
            $this->migrationCheck(),
            $this->cryptoCheck(),
        ]);
    }

    private function runtimeCheck(): DiagnosticCheck
    {
        $failures = $this->runtimeRequirements->check();
        if ($failures === []) {
            return new DiagnosticCheck('runtime.compatible', DiagnosticStatus::PASS, [
                'php' => PHP_VERSION,
                'prestashop' => defined('_PS_VERSION_') ? (string) constant('_PS_VERSION_') : 'unknown',
            ]);
        }

        return new DiagnosticCheck('runtime.incompatible', DiagnosticStatus::FAIL, [
            'failure_codes' => implode(',', array_map(
                static fn (RuntimeRequirementFailure $failure): string => $failure->code(),
                $failures,
            )),
        ]);
    }

    private function autoloadCheck(): DiagnosticCheck
    {
        $available = class_exists(\Qrk\Commerce\Shipping\ModuleMetadata::class)
            && class_exists(\Symfony\Component\HttpKernel\Kernel::class);

        return new DiagnosticCheck(
            $available ? 'autoload.available' : 'autoload.unavailable',
            $available ? DiagnosticStatus::PASS : DiagnosticStatus::FAIL,
        );
    }

    private function routingCheck(): DiagnosticCheck
    {
        $path = $this->moduleDirectory . '/config/routes.yml';
        $available = is_file($path) && is_readable($path);

        return new DiagnosticCheck(
            $available ? 'routing.available' : 'routing.unavailable',
            $available ? DiagnosticStatus::PASS : DiagnosticStatus::FAIL,
        );
    }

    private function translationCheck(): DiagnosticCheck
    {
        $files = glob($this->moduleDirectory . '/translations/*.xlf');
        $count = is_array($files) ? count($files) : 0;

        return new DiagnosticCheck(
            $count >= 8 ? 'translations.available' : 'translations.incomplete',
            $count >= 8 ? DiagnosticStatus::PASS : DiagnosticStatus::WARNING,
            ['xlf_files' => $count],
        );
    }

    private function schemaCheck(): DiagnosticCheck
    {
        try {
            $expected = count($this->schemaCatalog->tables());
            $existing = count($this->schemaInspector->existingExpectedTableNames($this->schemaCatalog));
            if ($existing !== $expected) {
                return new DiagnosticCheck('schema.incomplete', DiagnosticStatus::FAIL, [
                    'expected_tables' => $expected,
                    'existing_tables' => $existing,
                    'fingerprint' => $this->schemaCatalog->fingerprint(),
                ]);
            }

            $this->schemaInspector->assertCompatible($this->schemaCatalog);

            return new DiagnosticCheck('schema.compatible', DiagnosticStatus::PASS, [
                'tables' => $expected,
                'fingerprint' => $this->schemaCatalog->fingerprint(),
            ]);
        } catch (Throwable) {
            return new DiagnosticCheck('schema.incompatible', DiagnosticStatus::FAIL, [
                'fingerprint' => $this->schemaCatalog->fingerprint(),
            ]);
        }
    }

    private function migrationCheck(): DiagnosticCheck
    {
        try {
            $this->migrationRecorder->assertCurrent($this->schemaCatalog);
            $current = $this->migrationRecorder->current();

            return new DiagnosticCheck('migration.current', DiagnosticStatus::PASS, [
                'version' => $current['version'] ?? 'unknown',
                'applied_at' => $current['applied_at'] ?? 'unknown',
            ]);
        } catch (Throwable) {
            return new DiagnosticCheck('migration.invalid', DiagnosticStatus::FAIL);
        }
    }

    private function cryptoCheck(): DiagnosticCheck
    {
        try {
            $key = $this->masterKeyProvider->current();
            $locator = new SecretLocator(0, SettingScope::all(), 'diagnostic_probe');
            $plaintext = base64_encode(random_bytes(32));
            $aad = $locator->aad(1);
            $encrypted = $this->secretCipher->encrypt($plaintext, $aad, $key);
            $decrypted = $this->secretCipher->decrypt($encrypted, $aad, $key);

            if (!hash_equals($plaintext, $decrypted)) {
                throw new \RuntimeException('Cryptographic round-trip mismatch.');
            }

            return new DiagnosticCheck('crypto.available', DiagnosticStatus::PASS, [
                'cipher_version' => $encrypted->cipherVersion(),
                'key_id' => $encrypted->keyId(),
            ]);
        } catch (Throwable) {
            return new DiagnosticCheck('crypto.unavailable', DiagnosticStatus::FAIL);
        }
    }
}
