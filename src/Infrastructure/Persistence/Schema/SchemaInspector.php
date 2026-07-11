<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema;

use Qrk\Commerce\Shipping\Port\Persistence\DatabaseConnectionPort;

final class SchemaInspector
{
    private readonly Utf8mb4CollationResolver $collationResolver;

    public function __construct(
        private readonly DatabaseConnectionPort $connection,
        private readonly string $databasePrefix,
        ?Utf8mb4CollationResolver $collationResolver = null,
    ) {
        $this->collationResolver = $collationResolver ?? new Utf8mb4CollationResolver($connection);
        if (preg_match('/^[A-Za-z0-9_]*$/D', $databasePrefix) !== 1) {
            throw new IncompatibleSchemaException('Database prefix contains unsupported characters.');
        }
    }

    /**
     * @return list<string>
     */
    public function existingExpectedTableNames(SchemaCatalog $catalog): array
    {
        $existing = [];
        foreach ($catalog->tables() as $table) {
            $fullName = $table->fullName($this->databasePrefix);
            $this->assertIdentifierLength($fullName);
            if ($this->tableExists($fullName)) {
                $existing[] = $fullName;
            }
        }

        sort($existing);

        return $existing;
    }

    public function currentUtf8mb4Collation(): string
    {
        $collation = $this->collationResolver->resolve();
        if ($collation === null) {
            throw new IncompatibleSchemaException(
                'The database server does not provide a supported utf8mb4 collation.',
            );
        }

        return $collation;
    }

    public function assertCompatible(SchemaCatalog $catalog): void
    {
        foreach ($catalog->tables() as $table) {
            $this->assertTableCompatible($table);
        }
    }

    private function tableExists(string $fullName): bool
    {
        $row = $this->connection->fetchOne(sprintf(
            'SELECT `TABLE_NAME` FROM `INFORMATION_SCHEMA`.`TABLES` '
            . 'WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = %s',
            $this->connection->quote($fullName),
        ));

        return $row !== null;
    }

    private function assertTableCompatible(TableDefinition $definition): void
    {
        $fullName = $definition->fullName($this->databasePrefix);
        $this->assertIdentifierLength($fullName);

        $tableRow = $this->connection->fetchOne(sprintf(
            'SELECT `ENGINE`, `TABLE_COLLATION` FROM `INFORMATION_SCHEMA`.`TABLES` '
            . 'WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = %s',
            $this->connection->quote($fullName),
        ));

        if ($tableRow === null) {
            throw new IncompatibleSchemaException(sprintf('Required table "%s" is missing.', $fullName));
        }

        if (strtolower((string) ($tableRow['ENGINE'] ?? '')) !== 'innodb') {
            throw new IncompatibleSchemaException(sprintf('Table "%s" does not use InnoDB.', $fullName));
        }

        $collation = strtolower((string) ($tableRow['TABLE_COLLATION'] ?? ''));
        if (!str_starts_with($collation, 'utf8mb4_')) {
            throw new IncompatibleSchemaException(sprintf('Table "%s" does not use utf8mb4.', $fullName));
        }

        $this->assertColumns($definition, $fullName);
        $this->assertIndexes($definition, $fullName);
    }

    private function assertColumns(TableDefinition $definition, string $fullName): void
    {
        $rows = $this->connection->fetchAll(sprintf(
            'SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT`, `EXTRA` '
            . 'FROM `INFORMATION_SCHEMA`.`COLUMNS` '
            . 'WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = %s '
            . 'ORDER BY `ORDINAL_POSITION` ASC',
            $this->connection->quote($fullName),
        ));

        $expected = $definition->columns();
        if (count($rows) !== count($expected)) {
            throw new IncompatibleSchemaException(sprintf(
                'Table "%s" has an unexpected column count.',
                $fullName,
            ));
        }

        foreach ($expected as $position => $column) {
            $actual = $rows[$position];
            $actualCanonical = [
                'name' => (string) ($actual['COLUMN_NAME'] ?? ''),
                'type' => $this->normalizeColumnType((string) ($actual['COLUMN_TYPE'] ?? '')),
                'nullable' => strtoupper((string) ($actual['IS_NULLABLE'] ?? 'NO')) === 'YES',
                'default' => $this->normalizeActualDefault($actual['COLUMN_DEFAULT'] ?? null),
                'auto_increment' => str_contains(
                    strtolower((string) ($actual['EXTRA'] ?? '')),
                    'auto_increment',
                ),
            ];

            $expectedCanonical = $column->canonical();
            $expectedCanonical['type'] = $this->normalizeColumnType($expectedCanonical['type']);

            if ($actualCanonical !== $expectedCanonical) {
                throw new IncompatibleSchemaException(sprintf(
                    'Table "%s" column "%s" does not match the approved schema.',
                    $fullName,
                    $column->name(),
                ));
            }
        }
    }

    private function assertIndexes(TableDefinition $definition, string $fullName): void
    {
        $rows = $this->connection->fetchAll(sprintf(
            'SELECT `INDEX_NAME`, `NON_UNIQUE`, `SEQ_IN_INDEX`, `COLUMN_NAME` '
            . 'FROM `INFORMATION_SCHEMA`.`STATISTICS` '
            . 'WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = %s '
            . 'ORDER BY `INDEX_NAME` ASC, `SEQ_IN_INDEX` ASC',
            $this->connection->quote($fullName),
        ));

        /** @var array<string, array{name: string, columns: list<string>, unique: bool, primary: bool}> $actual */
        $actual = [];
        foreach ($rows as $row) {
            $name = (string) ($row['INDEX_NAME'] ?? '');
            if ($name === '') {
                continue;
            }

            $actual[$name] ??= [
                'name' => $name,
                'columns' => [],
                'unique' => (int) ($row['NON_UNIQUE'] ?? 1) === 0,
                'primary' => $name === 'PRIMARY',
            ];
            $actual[$name]['columns'][] = (string) ($row['COLUMN_NAME'] ?? '');
        }
        ksort($actual);

        $expected = [];
        foreach ($definition->indexes() as $index) {
            $expected[$index->name()] = $index->canonical();
        }
        ksort($expected);

        if (array_values($actual) !== array_values($expected)) {
            throw new IncompatibleSchemaException(sprintf(
                'Table "%s" indexes do not match the approved schema.',
                $fullName,
            ));
        }
    }

    private function normalizeColumnType(string $type): string
    {
        $type = strtolower(preg_replace('/\s+/', ' ', trim($type)) ?? $type);

        return preg_replace('/\b(bigint|int|smallint)\(\d+\)/', '$1', $type) ?? $type;
    }

    private function normalizeActualDefault(mixed $default): ?string
    {
        if ($default === null) {
            return null;
        }

        $normalized = (string) $default;

        return strtoupper($normalized) === 'NULL' ? null : $normalized;
    }

    private function assertIdentifierLength(string $identifier): void
    {
        if (strlen($identifier) > 64) {
            throw new IncompatibleSchemaException(sprintf(
                'Database prefix makes table identifier "%s" exceed the 64-character limit.',
                $identifier,
            ));
        }
    }
}
