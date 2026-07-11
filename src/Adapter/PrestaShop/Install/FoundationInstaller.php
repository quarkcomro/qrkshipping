<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Install;

use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;
use Qrk\Commerce\Shipping\Domain\Audit\AuditEvent;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaInstallAction;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaInstallationException;
use Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema\SchemaManager;
use Qrk\Commerce\Shipping\Port\Clock\ClockPort;
use Qrk\Commerce\Shipping\Port\Persistence\AuditEventRepositoryPort;
use Throwable;

final class FoundationInstaller
{
    public function __construct(
        private readonly SchemaManager $schemaManager,
        private readonly AuditEventRepositoryPort $auditEvents,
        private readonly ClockPort $clock,
    ) {
    }

    public function install(): SchemaInstallAction
    {
        $action = null;

        try {
            $action = $this->schemaManager->install();

            $this->auditEvents->append(new AuditEvent(
                'module.foundation_installed',
                'info',
                SettingScope::all(),
                AuditActor::system(),
                'schema',
                $this->schemaManager->fingerprint(),
                bin2hex(random_bytes(16)),
                [
                    'schema_action' => $action->value,
                    'schema_fingerprint' => $this->schemaManager->fingerprint(),
                ],
                $this->clock->now(),
            ));

            $this->schemaManager->assertHealthy();

            return $action;
        } catch (Throwable $exception) {
            if ($action !== SchemaInstallAction::CREATE) {
                throw $exception;
            }

            $rollbackFailures = $this->schemaManager->rollbackFreshInstallation();

            throw new SchemaInstallationException(
                'QRK Shipping foundation finalization failed and rollback was attempted.',
                $exception,
                $rollbackFailures,
            );
        }
    }
}
