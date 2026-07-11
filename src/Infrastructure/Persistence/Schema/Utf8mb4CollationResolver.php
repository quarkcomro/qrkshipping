<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema;

use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;

final class Utf8mb4CollationResolver
{
    /**
     * Restrict new tables to collations available on every supported database family.
     *
     * @var non-empty-list<string>
     */
    private const PORTABLE_COLLATIONS = [
        'utf8mb4_unicode_ci',
        'utf8mb4_general_ci',
    ];

    public function __construct(
        private readonly DatabaseConnectionPort $connection,
    ) {
    }

    public function resolve(): ?string
    {
        $row = $this->connection->fetchOne('SELECT @@collation_database AS `collation`');
        $databaseCollation = strtolower((string) ($row['collation'] ?? ''));

        if (in_array($databaseCollation, self::PORTABLE_COLLATIONS, true)) {
            return $databaseCollation;
        }

        foreach (self::PORTABLE_COLLATIONS as $collation) {
            if ($this->isAvailable($collation)) {
                return $collation;
            }
        }

        return null;
    }

    private function isAvailable(string $collation): bool
    {
        $row = $this->connection->fetchOne(sprintf(
            'SELECT `COLLATION_NAME` AS `collation` FROM `INFORMATION_SCHEMA`.`COLLATIONS` '
            . 'WHERE `CHARACTER_SET_NAME` = %s AND `COLLATION_NAME` = %s',
            $this->connection->quote('utf8mb4'),
            $this->connection->quote($collation),
        ));

        return strtolower((string) ($row['collation'] ?? '')) === $collation;
    }
}
