<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Install;

use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;
use Qrk\Commerce\Shipping\Domain\Audit\AuditEvent;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Port\Clock\ClockPort;
use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;
use Qrk\Commerce\Shipping\Port\Persistence\AuditEventRepositoryPort;
use Qrk\Commerce\Shipping\Port\Persistence\SecretRepositoryPort;
use Throwable;

final class FoundationUninstaller
{
    public function __construct(
        private readonly DatabaseConnectionPort $connection,
        private readonly SecretRepositoryPort $secrets,
        private readonly AuditEventRepositoryPort $auditEvents,
        private readonly ClockPort $clock,
        private readonly string $databasePrefix,
    ) {
    }

    public function removeSecretsAndKeepData(): int
    {
        if (!$this->tableExists($this->databasePrefix . 'qrkship_secret')) {
            return 0;
        }

        $removed = $this->secrets->deleteAll();

        if ($this->tableExists($this->databasePrefix . 'qrkship_audit_event')) {
            try {
                $this->auditEvents->append(new AuditEvent(
                    'module.uninstalled_secrets_removed',
                    'info',
                    SettingScope::all(),
                    AuditActor::system(),
                    'module',
                    'qrkshipping',
                    bin2hex(random_bytes(16)),
                    [
                        'removed_secret_rows' => $removed,
                        'non_secret_data_retained' => true,
                    ],
                    $this->clock->now(),
                ));
            } catch (Throwable) {
                // Secret deletion is mandatory. A failed best-effort audit must not restore or expose secrets.
            }
        }

        return $removed;
    }

    private function tableExists(string $tableName): bool
    {
        return $this->connection->fetchOne(sprintf(
            'SELECT `TABLE_NAME` FROM `INFORMATION_SCHEMA`.`TABLES` '
            . 'WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = %s',
            $this->connection->quote($tableName),
        )) !== null;
    }
}
