<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Repository;

use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;
use Qrk\Commerce\Shipping\Domain\Audit\AuditEvent;
use Qrk\Commerce\Shipping\Port\Persistence\AuditEventRepositoryPort;

final class DbAuditEventRepository implements AuditEventRepositoryPort
{
    private readonly string $table;

    public function __construct(
        private readonly DatabaseConnectionPort $connection,
        private readonly ScopePersistenceMapper $scopeMapper,
        string $databasePrefix,
    ) {
        self::assertPrefix($databasePrefix);
        $this->table = $databasePrefix . 'qrkship_audit_event';
    }

    public function append(AuditEvent $event): void
    {
        $scope = $this->scopeMapper->toColumns($event->scope());

        $this->connection->execute(sprintf(
            'INSERT INTO `%s` '
            . '(`event_code`, `severity`, `scope_type`, `id_shop_group`, `id_shop`, '
            . '`actor_type`, `actor_id`, `subject_type`, `subject_id`, `correlation_id`, '
            . '`metadata_text`, `occurred_at`) '
            . 'VALUES (%s, %s, %s, %d, %d, %s, %d, %s, %s, %s, %s, %s)',
            $this->table,
            $this->connection->quote($event->eventCode()),
            $this->connection->quote($event->severity()),
            $this->connection->quote($scope['scope_type']),
            $scope['id_shop_group'],
            $scope['id_shop'],
            $this->connection->quote($event->actor()->type()),
            $event->actor()->id(),
            $this->connection->quote($event->subjectType()),
            $this->connection->quote($event->subjectId()),
            $this->connection->quote($event->correlationId()),
            $this->connection->quote($event->metadataJson()),
            $this->connection->quote($event->occurredAt()->format('Y-m-d H:i:s')),
        ));
    }

    private static function assertPrefix(string $databasePrefix): void
    {
        if (preg_match('/^[A-Za-z0-9_]*$/D', $databasePrefix) !== 1) {
            throw new \InvalidArgumentException('Database prefix is invalid.');
        }
    }
}
