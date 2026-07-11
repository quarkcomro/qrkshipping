<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Money;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Domain\Money\CurrencyCode;
use Qrk\Commerce\Shipping\Domain\Money\DecimalAmount;
use Qrk\Commerce\Shipping\Domain\Money\ExchangeRateDirection;
use Qrk\Commerce\Shipping\Domain\Money\ExchangeRateSnapshot;
use Qrk\Commerce\Shipping\Domain\Money\Money;
use Qrk\Commerce\Shipping\Domain\Money\RoundingMode;

final class MoneyAndExchangeRateTest extends TestCase
{
    public function testMoneyOperationsRequireOneCurrency(): void
    {
        $sum = Money::fromStrings('10.20', 'ron')->add(Money::fromStrings('2.30', 'RON'));
        self::assertSame('12.5', (string) $sum->amount());
        self::assertSame('RON', $sum->currency()->value());

        $this->expectException(DomainException::class);
        Money::fromStrings('1', 'RON')->add(Money::fromStrings('1', 'EUR'));
    }

    public function testExchangeRateSnapshotRetainsExactDirectionAndPolicy(): void
    {
        $capturedAt = new DateTimeImmutable('2026-07-11T10:00:00+00:00', new DateTimeZone('UTC'));
        $snapshot = new ExchangeRateSnapshot(
            CurrencyCode::fromString('EUR'),
            CurrencyCode::fromString('RON'),
            DecimalAmount::fromString('4.976500'),
            ExchangeRateDirection::SOURCE_TO_TARGET,
            '  PrestaShop currency snapshot  ',
            $capturedAt,
            RoundingMode::HALF_UP,
            2,
        );

        self::assertSame('4.9765', (string) $snapshot->rate());
        self::assertSame('PrestaShop currency snapshot', $snapshot->source());
        self::assertSame(2, $snapshot->targetScale());
        self::assertSame(ExchangeRateDirection::SOURCE_TO_TARGET, $snapshot->direction());
    }
}
