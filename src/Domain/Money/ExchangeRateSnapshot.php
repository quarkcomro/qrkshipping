<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Money;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ExchangeRateSnapshot
{
    private CurrencyCode $sourceCurrency;
    private CurrencyCode $targetCurrency;
    private DecimalAmount $rate;
    private ExchangeRateDirection $direction;
    private string $source;
    private DateTimeImmutable $capturedAt;
    private RoundingMode $roundingMode;
    private int $targetScale;

    public function __construct(
        CurrencyCode $sourceCurrency,
        CurrencyCode $targetCurrency,
        DecimalAmount $rate,
        ExchangeRateDirection $direction,
        string $source,
        DateTimeImmutable $capturedAt,
        RoundingMode $roundingMode,
        int $targetScale,
    ) {
        if (!$rate->isPositive()) {
            throw new InvalidArgumentException('Exchange rate must be strictly positive.');
        }

        $source = trim($source);
        if ($source === '' || strlen($source) > 128) {
            throw new InvalidArgumentException('Exchange-rate source must contain between 1 and 128 characters.');
        }

        if ($targetScale < 0 || $targetScale > DecimalAmount::MAX_SCALE) {
            throw new InvalidArgumentException('Exchange-rate target scale is outside the supported range.');
        }

        $this->sourceCurrency = $sourceCurrency;
        $this->targetCurrency = $targetCurrency;
        $this->rate = $rate;
        $this->direction = $direction;
        $this->source = $source;
        $this->capturedAt = $capturedAt;
        $this->roundingMode = $roundingMode;
        $this->targetScale = $targetScale;
    }

    public function sourceCurrency(): CurrencyCode
    {
        return $this->sourceCurrency;
    }

    public function targetCurrency(): CurrencyCode
    {
        return $this->targetCurrency;
    }

    public function rate(): DecimalAmount
    {
        return $this->rate;
    }

    public function direction(): ExchangeRateDirection
    {
        return $this->direction;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function capturedAt(): DateTimeImmutable
    {
        return $this->capturedAt;
    }

    public function roundingMode(): RoundingMode
    {
        return $this->roundingMode;
    }

    public function targetScale(): int
    {
        return $this->targetScale;
    }
}
