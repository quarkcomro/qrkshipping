<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Audit;

use InvalidArgumentException;

final readonly class AuditActor
{
    private function __construct(
        private string $type,
        private int $id,
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{1,31}$/D', $type) !== 1) {
            throw new InvalidArgumentException('Audit actor type is invalid.');
        }

        if ($id < 0) {
            throw new InvalidArgumentException('Audit actor ID cannot be negative.');
        }
    }

    public static function employee(int $employeeId): self
    {
        if ($employeeId <= 0) {
            throw new InvalidArgumentException('Audit employee ID must be positive.');
        }

        return new self('employee', $employeeId);
    }

    public static function system(): self
    {
        return new self('system', 0);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function id(): int
    {
        return $this->id;
    }
}
