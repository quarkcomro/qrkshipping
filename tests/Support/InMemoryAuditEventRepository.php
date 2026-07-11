<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Support;

use Qrk\Commerce\Shipping\Domain\Audit\AuditEvent;
use Qrk\Commerce\Shipping\Port\Persistence\AuditEventRepositoryPort;

final class InMemoryAuditEventRepository implements AuditEventRepositoryPort
{
    /** @var list<AuditEvent> */
    public array $events = [];

    public function append(AuditEvent $event): void
    {
        $this->events[] = $event;
    }
}
