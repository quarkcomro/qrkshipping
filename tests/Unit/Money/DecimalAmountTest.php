<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Money;

use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qrk\Commerce\Shipping\Domain\Money\DecimalAmount;
use Qrk\Commerce\Shipping\Domain\Money\RoundingMode;

final class DecimalAmountTest extends TestCase
{
    #[DataProvider('normalizationCases')]
    public function testNormalizesPlainDecimalStrings(string $input, string $expected): void
    {
        self::assertSame($expected, (string) DecimalAmount::fromString($input));
    }

    /** @return iterable<string, array{string, string}> */
    public static function normalizationCases(): iterable
    {
        yield 'zero' => ['-0.000000', '0'];
        yield 'leading zeros' => ['+00012.340000', '12.34'];
        yield 'fraction' => ['0.000001', '0.000001'];
        yield 'negative' => [' -001.25 ', '-1.25'];
    }

    public function testRejectsNonPlainOrOutOfRangeValues(): void
    {
        foreach (['1e2', '1,20', 'NaN', '0.0000001'] as $invalid) {
            try {
                DecimalAmount::fromString($invalid);
                self::fail('Expected invalid decimal rejection for ' . $invalid);
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }

        $this->expectException(OverflowException::class);
        DecimalAmount::fromString('100000000000000.000000');
    }

    public function testAdditionAndSubtractionAreExactAcrossScales(): void
    {
        self::assertSame('12.375', (string) DecimalAmount::fromString('10.25')->add(
            DecimalAmount::fromString('2.125'),
        ));
        self::assertSame('-0.125', (string) DecimalAmount::fromString('2')->subtract(
            DecimalAmount::fromString('2.125'),
        ));
        self::assertSame('0', (string) DecimalAmount::fromString('-2.5')->add(
            DecimalAmount::fromString('2.500000'),
        ));
    }

    public function testDeterministicIntegerBackedPropertyCases(): void
    {
        mt_srand(914);
        for ($case = 0; $case < 500; ++$case) {
            $left = mt_rand(-9_000_000, 9_000_000);
            $right = mt_rand(-9_000_000, 9_000_000);
            $leftDecimal = DecimalAmount::fromString($this->scaledString($left, 3));
            $rightDecimal = DecimalAmount::fromString($this->scaledString($right, 3));

            self::assertSame(
                $this->normalizedScaledString($left + $right, 3),
                (string) $leftDecimal->add($rightDecimal),
            );
            self::assertSame(
                $this->normalizedScaledString($left - $right, 3),
                (string) $leftDecimal->subtract($rightDecimal),
            );
            self::assertTrue($leftDecimal->add($rightDecimal)->equals($rightDecimal->add($leftDecimal)));
        }
    }

    #[DataProvider('roundingCases')]
    public function testRounding(string $input, int $scale, RoundingMode $mode, string $expected): void
    {
        self::assertSame($expected, (string) DecimalAmount::fromString($input)->round($scale, $mode));
    }

    /** @return iterable<string, array{string, int, RoundingMode, string}> */
    public static function roundingCases(): iterable
    {
        yield 'half up positive' => ['1.235', 2, RoundingMode::HALF_UP, '1.24'];
        yield 'half up negative' => ['-1.235', 2, RoundingMode::HALF_UP, '-1.24'];
        yield 'half even down' => ['1.225', 2, RoundingMode::HALF_EVEN, '1.22'];
        yield 'half even up' => ['1.235', 2, RoundingMode::HALF_EVEN, '1.24'];
        yield 'toward zero' => ['-1.239', 2, RoundingMode::DOWN, '-1.23'];
        yield 'away from zero' => ['-1.231', 2, RoundingMode::UP, '-1.24'];
    }

    public function testFixedScaleNeverRoundsImplicitly(): void
    {
        self::assertSame('12.340000', DecimalAmount::fromString('12.34')->toFixedScale(6));

        $this->expectException(InvalidArgumentException::class);
        DecimalAmount::fromString('12.34')->toFixedScale(1);
    }

    private function scaledString(int $value, int $scale): string
    {
        $negative = $value < 0;
        $digits = str_pad((string) abs($value), $scale + 1, '0', STR_PAD_LEFT);
        $text = substr($digits, 0, -$scale) . '.' . substr($digits, -$scale);

        return $negative ? '-' . $text : $text;
    }

    private function normalizedScaledString(int $value, int $scale): string
    {
        return (string) DecimalAmount::fromString($this->scaledString($value, $scale));
    }
}
