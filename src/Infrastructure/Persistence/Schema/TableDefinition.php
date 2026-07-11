<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema;

use InvalidArgumentException;

final readonly class TableDefinition
{
    /**
     * @param non-empty-list<ColumnDefinition> $columns
     * @param non-empty-list<IndexDefinition> $indexes
     */
    public function __construct(
        private string $suffix,
        private array $columns,
        private array $indexes,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $suffix) !== 1) {
            throw new InvalidArgumentException('Schema table suffix is invalid.');
        }

        $columnNames = [];
        foreach ($columns as $column) {
            if (isset($columnNames[$column->name()])) {
                throw new InvalidArgumentException('Duplicate schema column.');
            }
            $columnNames[$column->name()] = true;
        }

        $indexNames = [];
        foreach ($indexes as $index) {
            if (isset($indexNames[$index->name()])) {
                throw new InvalidArgumentException('Duplicate schema index.');
            }
            $indexNames[$index->name()] = true;

            foreach ($index->columns() as $column) {
                if (!isset($columnNames[$column])) {
                    throw new InvalidArgumentException('Schema index references an unknown column.');
                }
            }
        }
    }

    public function suffix(): string
    {
        return $this->suffix;
    }

    /**
     * @return non-empty-list<ColumnDefinition>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * @return non-empty-list<IndexDefinition>
     */
    public function indexes(): array
    {
        return $this->indexes;
    }

    public function fullName(string $databasePrefix): string
    {
        return $databasePrefix . $this->suffix;
    }

    public function createSql(string $databasePrefix, string $collation): string
    {
        $parts = [
            ...array_map(
                static fn (ColumnDefinition $column): string => $column->ddl(),
                $this->columns,
            ),
            ...array_map(
                static fn (IndexDefinition $index): string => $index->ddl(),
                $this->indexes,
            ),
        ];

        return sprintf(
            "CREATE TABLE `%s` (\n  %s\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=%s",
            $this->fullName($databasePrefix),
            implode(",\n  ", $parts),
            $collation,
        );
    }

    /**
     * @return array{
     *   suffix: string,
     *   engine: string,
     *   charset: string,
     *   columns: list<array{name: string, type: string, nullable: bool, default: string|null, auto_increment: bool}>,
     *   indexes: list<array{name: string, columns: non-empty-list<string>, unique: bool, primary: bool}>
     * }
     */
    public function canonical(): array
    {
        return [
            'suffix' => $this->suffix,
            'engine' => 'innodb',
            'charset' => 'utf8mb4',
            'columns' => array_map(
                static fn (ColumnDefinition $column): array => $column->canonical(),
                $this->columns,
            ),
            'indexes' => array_map(
                static fn (IndexDefinition $index): array => $index->canonical(),
                $this->indexes,
            ),
        ];
    }
}
