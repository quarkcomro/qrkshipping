<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Infrastructure\Persistence\Schema;

use InvalidArgumentException;

final readonly class ColumnDefinition
{
    public function __construct(
        private string $name,
        private string $type,
        private bool $nullable = false,
        private ?string $default = null,
        private bool $autoIncrement = false,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Schema column name is invalid.');
        }

        if ($type === '') {
            throw new InvalidArgumentException('Schema column type cannot be empty.');
        }

        if ($autoIncrement && $nullable) {
            throw new InvalidArgumentException('Auto-increment columns cannot be nullable.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function normalizedType(): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($this->type)) ?? $this->type);
    }

    public function nullable(): bool
    {
        return $this->nullable;
    }

    public function default(): ?string
    {
        return $this->default;
    }

    public function autoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    public function ddl(): string
    {
        $sql = sprintf('`%s` %s %s', $this->name, $this->type, $this->nullable ? 'NULL' : 'NOT NULL');

        if ($this->default !== null) {
            $sql .= ' DEFAULT ' . $this->default;
        } elseif ($this->nullable) {
            $sql .= ' DEFAULT NULL';
        }

        if ($this->autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }

        return $sql;
    }

    /**
     * @return array{name: string, type: string, nullable: bool, default: string|null, auto_increment: bool}
     */
    public function canonical(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->normalizedType(),
            'nullable' => $this->nullable,
            'default' => $this->normalizedDefault(),
            'auto_increment' => $this->autoIncrement,
        ];
    }

    public function normalizedDefault(): ?string
    {
        if ($this->default === null) {
            return null;
        }

        $default = trim($this->default);
        if (strlen($default) >= 2 && $default[0] === "'" && $default[strlen($default) - 1] === "'") {
            return str_replace("''", "'", substr($default, 1, -1));
        }

        return $default;
    }
}
