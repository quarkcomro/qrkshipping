<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Money;

use DomainException;

final readonly class Money
{
    public function __construct(
        private DecimalAmount $amount,
        private CurrencyCode $currency,
    ) {
    }

    public static function fromStrings(string $amount, string $currency): self
    {
        return new self(DecimalAmount::fromString($amount), CurrencyCode::fromString($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount->add($other->amount), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount->subtract($other->amount), $this->currency);
    }

    public function round(int $scale, RoundingMode $mode): self
    {
        return new self($this->amount->round($scale, $mode), $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->currency->equals($other->currency) && $this->amount->equals($other->amount);
    }

    public function amount(): DecimalAmount
    {
        return $this->amount;
    }

    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if (!$this->currency->equals($other->currency)) {
            throw new DomainException(sprintf(
                'Money operation requires the same currency; received %s and %s.',
                $this->currency,
                $other->currency,
            ));
        }
    }
}
