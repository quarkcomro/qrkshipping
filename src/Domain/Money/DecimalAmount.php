<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Money;

use InvalidArgumentException;
use OverflowException;
use Stringable;

final readonly class DecimalAmount implements Stringable
{
    public const MAX_PRECISION = 20;
    public const MAX_SCALE = 6;
    private const MAX_INTEGER_DIGITS = self::MAX_PRECISION - self::MAX_SCALE;

    private function __construct(
        private bool $negative,
        private string $digits,
        private int $scale,
    ) {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);
        if (preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/D', $value, $matches) !== 1) {
            throw new InvalidArgumentException('Decimal value must use a plain base-10 string.');
        }

        $fraction = $matches[3] ?? '';
        if (strlen($fraction) > self::MAX_SCALE) {
            throw new InvalidArgumentException(sprintf(
                'Decimal scale exceeds the supported maximum of %d.',
                self::MAX_SCALE,
            ));
        }

        $integer = ltrim($matches[2], '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = rtrim($fraction, '0');

        self::assertFits($integer, $fraction);

        if ($integer === '0' && $fraction === '') {
            return new self(false, '0', 0);
        }

        $digits = ltrim($integer . $fraction, '0');
        $digits = $digits === '' ? '0' : $digits;

        return new self($matches[1] === '-', $digits, strlen($fraction));
    }

    public static function zero(): self
    {
        return new self(false, '0', 0);
    }

    public function add(self $other): self
    {
        [$left, $right, $scale] = $this->align($other);

        if ($this->negative === $other->negative) {
            return self::fromSignedDigits($this->negative, self::addAbsolute($left, $right), $scale);
        }

        $comparison = self::compareAbsolute($left, $right);
        if ($comparison === 0) {
            return self::zero();
        }

        if ($comparison > 0) {
            return self::fromSignedDigits($this->negative, self::subtractAbsolute($left, $right), $scale);
        }

        return self::fromSignedDigits($other->negative, self::subtractAbsolute($right, $left), $scale);
    }

    public function subtract(self $other): self
    {
        return $this->add($other->negate());
    }

    public function negate(): self
    {
        if ($this->isZero()) {
            return $this;
        }

        return new self(!$this->negative, $this->digits, $this->scale);
    }

    public function absolute(): self
    {
        return $this->negative ? new self(false, $this->digits, $this->scale) : $this;
    }

    public function round(int $targetScale, RoundingMode $mode): self
    {
        if ($targetScale < 0 || $targetScale > self::MAX_SCALE) {
            throw new InvalidArgumentException(sprintf(
                'Target scale must be between 0 and %d.',
                self::MAX_SCALE,
            ));
        }

        if ($targetScale >= $this->scale) {
            return $this;
        }

        $discardedCount = $this->scale - $targetScale;
        $padded = str_pad($this->digits, $this->scale + 1, '0', STR_PAD_LEFT);
        $cut = strlen($padded) - $discardedCount;
        $kept = substr($padded, 0, $cut);
        $discarded = substr($padded, $cut);
        $kept = ltrim($kept, '0');
        $kept = $kept === '' ? '0' : $kept;

        if ($this->shouldIncrement($kept, $discarded, $mode)) {
            $kept = self::addAbsolute($kept, '1');
        }

        return self::fromSignedDigits($this->negative, $kept, $targetScale);
    }

    public function compare(self $other): int
    {
        if ($this->negative !== $other->negative) {
            return $this->negative ? -1 : 1;
        }

        [$left, $right] = $this->align($other);
        $comparison = self::compareAbsolute($left, $right);

        return $this->negative ? -$comparison : $comparison;
    }

    public function equals(self $other): bool
    {
        return $this->compare($other) === 0;
    }

    public function isZero(): bool
    {
        return $this->digits === '0';
    }

    public function isPositive(): bool
    {
        return !$this->negative && !$this->isZero();
    }

    public function isNegative(): bool
    {
        return $this->negative;
    }

    public function scale(): int
    {
        return $this->scale;
    }

    public function toFixedScale(int $scale): string
    {
        if ($scale < $this->scale || $scale > self::MAX_SCALE) {
            throw new InvalidArgumentException('Fixed output scale cannot discard digits or exceed the maximum scale.');
        }

        [$integer, $fraction] = $this->parts();
        $fraction = str_pad($fraction, $scale, '0');

        $value = $scale === 0 ? $integer : $integer . '.' . $fraction;

        return $this->negative ? '-' . $value : $value;
    }

    public function __toString(): string
    {
        [$integer, $fraction] = $this->parts();
        $value = $fraction === '' ? $integer : $integer . '.' . $fraction;

        return $this->negative ? '-' . $value : $value;
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private function align(self $other): array
    {
        $scale = max($this->scale, $other->scale);

        return [
            $this->digits . str_repeat('0', $scale - $this->scale),
            $other->digits . str_repeat('0', $scale - $other->scale),
            $scale,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parts(): array
    {
        if ($this->scale === 0) {
            return [$this->digits, ''];
        }

        $padded = str_pad($this->digits, $this->scale + 1, '0', STR_PAD_LEFT);

        return [
            substr($padded, 0, -$this->scale),
            substr($padded, -$this->scale),
        ];
    }

    private function shouldIncrement(string $kept, string $discarded, RoundingMode $mode): bool
    {
        if ($discarded === '' || strspn($discarded, '0') === strlen($discarded)) {
            return false;
        }

        return match ($mode) {
            RoundingMode::DOWN => false,
            RoundingMode::UP => true,
            RoundingMode::HALF_UP => $discarded[0] >= '5',
            RoundingMode::HALF_EVEN => $this->shouldRoundHalfEven($kept, $discarded),
        };
    }

    private function shouldRoundHalfEven(string $kept, string $discarded): bool
    {
        if ($discarded[0] > '5') {
            return true;
        }

        if ($discarded[0] < '5') {
            return false;
        }

        if (strspn(substr($discarded, 1), '0') !== strlen($discarded) - 1) {
            return true;
        }

        $lastKeptDigit = $kept[strlen($kept) - 1];

        return ((int) $lastKeptDigit) % 2 === 1;
    }

    private static function fromSignedDigits(bool $negative, string $digits, int $scale): self
    {
        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;

        while ($scale > 0 && str_ends_with($digits, '0')) {
            $digits = substr($digits, 0, -1);
            --$scale;
        }

        if ($digits === '' || $digits === '0') {
            return self::zero();
        }

        $padded = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $integer = $scale === 0 ? $padded : substr($padded, 0, -$scale);
        $fraction = $scale === 0 ? '' : substr($padded, -$scale);
        self::assertFits($integer, $fraction);

        return new self($negative, $digits, $scale);
    }

    private static function assertFits(string $integer, string $fraction): void
    {
        $normalizedInteger = ltrim($integer, '0');
        $integerDigits = max(1, strlen($normalizedInteger));

        if ($integerDigits > self::MAX_INTEGER_DIGITS) {
            throw new OverflowException(sprintf(
                'Decimal integer part exceeds %d digits.',
                self::MAX_INTEGER_DIGITS,
            ));
        }

        if ($integerDigits + strlen($fraction) > self::MAX_PRECISION) {
            throw new OverflowException(sprintf(
                'Decimal precision exceeds %d digits.',
                self::MAX_PRECISION,
            ));
        }
    }

    private static function compareAbsolute(string $left, string $right): int
    {
        $left = ltrim($left, '0');
        $right = ltrim($right, '0');
        $left = $left === '' ? '0' : $left;
        $right = $right === '' ? '0' : $right;

        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        return $left <=> $right;
    }

    private static function addAbsolute(string $left, string $right): string
    {
        $carry = 0;
        $result = '';
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;

        while ($leftIndex >= 0 || $rightIndex >= 0 || $carry > 0) {
            $sum = $carry;
            if ($leftIndex >= 0) {
                $sum += (int) $left[$leftIndex--];
            }
            if ($rightIndex >= 0) {
                $sum += (int) $right[$rightIndex--];
            }

            $result = (string) ($sum % 10) . $result;
            $carry = intdiv($sum, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    private static function subtractAbsolute(string $larger, string $smaller): string
    {
        $borrow = 0;
        $result = '';
        $largerIndex = strlen($larger) - 1;
        $smallerIndex = strlen($smaller) - 1;

        while ($largerIndex >= 0) {
            $digit = (int) $larger[$largerIndex--] - $borrow;
            $subtract = $smallerIndex >= 0 ? (int) $smaller[$smallerIndex--] : 0;

            if ($digit < $subtract) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result = (string) ($digit - $subtract) . $result;
        }

        return ltrim($result, '0') ?: '0';
    }
}
