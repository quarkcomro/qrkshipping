<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Money;

use InvalidArgumentException;
use Stringable;

final readonly class CurrencyCode implements Stringable
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));
        if (preg_match('/^[A-Z]{3}$/D', $normalized) !== 1) {
            throw new InvalidArgumentException('Currency code must contain exactly three ASCII letters.');
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
