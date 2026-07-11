<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Provider;

use InvalidArgumentException;
use Stringable;

final readonly class ProviderCode implements Stringable
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $normalized) !== 1) {
            throw new InvalidArgumentException('Provider code is invalid.');
        }

        return new self($normalized);
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
