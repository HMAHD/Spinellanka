<?php

declare(strict_types=1);

namespace App\Domain\Pricing\ValueObjects;

use App\Domain\Pricing\Exceptions\CurrencyMismatchException;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final class Money implements JsonSerializable, Stringable
{
    public function __construct(
        public readonly int $amountMinorUnits,
        public readonly string $currency = 'USD'
    ) {}

    public static function of(int $minorUnits, string $currency = 'USD'): self
    {
        return new self($minorUnits, strtoupper($currency));
    }

    /**
     * Parse dollar string or integer without IEEE-754 floating point arithmetic.
     */
    public static function ofDollars(string|int $dollars, string $currency = 'USD'): self
    {
        if (is_int($dollars)) {
            return new self($dollars * 100, strtoupper($currency));
        }

        $trimmed = trim($dollars);
        if ($trimmed === '' || ! preg_match('/^-?\d+(\.\d{1,2})?$/', $trimmed)) {
            throw new InvalidArgumentException("Invalid dollar amount format: '{$dollars}'.");
        }

        $isNegative = str_starts_with($trimmed, '-');
        $clean = ltrim($trimmed, '-');
        $parts = explode('.', $clean);
        $whole = (int) $parts[0];
        $cents = isset($parts[1]) ? (int) str_pad(substr($parts[1], 0, 2), 2, '0') : 0;

        $minorUnits = ($whole * 100) + $cents;

        return new self($isNegative ? -$minorUnits : $minorUnits, strtoupper($currency));
    }

    public static function zero(string $currency = 'USD'): self
    {
        return new self(0, strtoupper($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinorUnits + $other->amountMinorUnits, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinorUnits - $other->amountMinorUnits, $this->currency);
    }

    public function multiply(int $multiplier): self
    {
        return new self($this->amountMinorUnits * $multiplier, $this->currency);
    }

    /**
     * Calculate percentage using integer basis points (1% = 100 bps, 15% = 1500 bps).
     *
     * @param  1|2|3|4  $roundingMode
     */
    public function multiplyByBasisPoints(int $basisPoints, int $roundingMode = PHP_ROUND_HALF_UP): self
    {
        $product = $this->amountMinorUnits * $basisPoints;
        $rounded = (int) round($product / 10000, 0, $roundingMode);

        return new self($rounded, $this->currency);
    }

    /**
     * Allocate money across integer ratios without losing cents.
     *
     * @param  array<int>  $ratios
     * @return array<self>
     */
    public function allocate(array $ratios): array
    {
        if (empty($ratios)) {
            throw new InvalidArgumentException('Cannot allocate across empty ratios.');
        }

        $totalRatio = array_sum($ratios);
        if ($totalRatio <= 0) {
            throw new InvalidArgumentException('Total ratio must be greater than zero.');
        }

        $remainder = $this->amountMinorUnits;
        $results = [];

        foreach ($ratios as $ratio) {
            $share = (int) intval(($this->amountMinorUnits * $ratio) / $totalRatio);
            $results[] = $share;
            $remainder -= $share;
        }

        for ($i = 0; $i < $remainder; $i++) {
            $results[$i]++;
        }

        return array_map(fn (int $amount) => new self($amount, $this->currency), $results);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->amountMinorUnits === $other->amountMinorUnits;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountMinorUnits > $other->amountMinorUnits;
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountMinorUnits < $other->amountMinorUnits;
    }

    public function isGreaterThanOrEqualTo(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountMinorUnits >= $other->amountMinorUnits;
    }

    public function isLessThanOrEqualTo(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountMinorUnits <= $other->amountMinorUnits;
    }

    public function isZero(): bool
    {
        return $this->amountMinorUnits === 0;
    }

    public function isPositive(): bool
    {
        return $this->amountMinorUnits > 0;
    }

    public function isNegative(): bool
    {
        return $this->amountMinorUnits < 0;
    }

    public function format(): string
    {
        $sign = $this->amountMinorUnits < 0 ? '-' : '';
        $abs = abs($this->amountMinorUnits);
        $dollars = intdiv($abs, 100);
        $cents = str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        if ($this->currency === 'USD') {
            return "{$sign}$".number_format($dollars).".{$cents}";
        }

        return "{$sign}{$this->currency} ".number_format($dollars).".{$cents}";
    }

    /**
     * @return array{amount_minor_units: int, currency: string, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'amount_minor_units' => $this->amountMinorUnits,
            'currency' => $this->currency,
            'formatted' => $this->format(),
        ];
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatchException($this->currency, $other->currency);
        }
    }
}
