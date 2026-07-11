<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema;

use InvalidArgumentException;

final readonly class IndexDefinition
{
    /**
     * @param non-empty-list<string> $columns
     */
    public function __construct(
        private string $name,
        private array $columns,
        private bool $unique = false,
        private bool $primary = false,
    ) {
        if ($primary && $name !== 'PRIMARY') {
            throw new InvalidArgumentException('Primary index must use the PRIMARY name.');
        }

        if (!$primary && preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Schema index name is invalid.');
        }

        if ($columns === []) {
            throw new InvalidArgumentException('Schema index requires at least one column.');
        }

        foreach ($columns as $column) {
            if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $column) !== 1) {
                throw new InvalidArgumentException('Schema index column is invalid.');
            }
        }
    }

    /**
     * @param non-empty-list<string> $columns
     */
    public static function primary(array $columns): self
    {
        return new self('PRIMARY', $columns, true, true);
    }

    /**
     * @param non-empty-list<string> $columns
     */
    public static function unique(string $name, array $columns): self
    {
        return new self($name, $columns, true, false);
    }

    /**
     * @param non-empty-list<string> $columns
     */
    public static function regular(string $name, array $columns): self
    {
        return new self($name, $columns, false, false);
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return non-empty-list<string>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function ddl(): string
    {
        $columns = implode(', ', array_map(
            static fn (string $column): string => sprintf('`%s`', $column),
            $this->columns,
        ));

        if ($this->primary) {
            return 'PRIMARY KEY (' . $columns . ')';
        }

        return sprintf(
            '%sKEY `%s` (%s)',
            $this->unique ? 'UNIQUE ' : '',
            $this->name,
            $columns,
        );
    }

    /**
     * @return array{name: string, columns: non-empty-list<string>, unique: bool, primary: bool}
     */
    public function canonical(): array
    {
        return [
            'name' => $this->name,
            'columns' => $this->columns,
            'unique' => $this->unique,
            'primary' => $this->primary,
        ];
    }
}
