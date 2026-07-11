<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Port\Persistence;

use Qrk\Commerce\Shipping\Domain\Audit\AuditEvent;

interface AuditEventRepositoryPort
{
    public function append(AuditEvent $event): void;
}
