<?php

declare(strict_types=1);

use App\Domain\Pricing\Exceptions\CurrencyMismatchException;
use App\Domain\Pricing\ValueObjects\Money;

test('money can be constructed from minor units', function (): void {
    $money = Money::of(10050, 'USD');

    expect($money->amountMinorUnits)->toBe(10050)
        ->and($money->currency)->toBe('USD')
        ->and($money->format())->toBe('$100.50');
});

test('money parses string dollars without floating point errors', function (): void {
    $money = Money::ofDollars('19.99', 'USD');
    expect($money->amountMinorUnits)->toBe(1999);

    $integerDollar = Money::ofDollars(50, 'USD');
    expect($integerDollar->amountMinorUnits)->toBe(5000);

    $negativeMoney = Money::ofDollars('-15.50', 'USD');
    expect($negativeMoney->amountMinorUnits)->toBe(-1550)
        ->and($negativeMoney->format())->toBe('-$15.50');
});

test('money rejects invalid dollar string formats', function (): void {
    expect(fn () => Money::ofDollars('abc'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Money::ofDollars('10.999'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Money::ofDollars(''))->toThrow(InvalidArgumentException::class);
});

test('money supports addition and subtraction', function (): void {
    $a = Money::of(5000, 'USD');
    $b = Money::of(2550, 'USD');

    $sum = $a->add($b);
    expect($sum->amountMinorUnits)->toBe(7550);

    $diff = $a->subtract($b);
    expect($diff->amountMinorUnits)->toBe(2450);
});

test('money throws CurrencyMismatchException on cross-currency arithmetic', function (): void {
    $usd = Money::of(1000, 'USD');
    $lkr = Money::of(1000, 'LKR');

    expect(fn () => $usd->add($lkr))->toThrow(CurrencyMismatchException::class)
        ->and(fn () => $usd->subtract($lkr))->toThrow(CurrencyMismatchException::class);
});

test('money multiplies with basis points for percentage calculations', function (): void {
    $subtotal = Money::of(10000, 'USD'); // $100.00

    // 15% tax = 1500 basis points -> $15.00
    $tax = $subtotal->multiplyByBasisPoints(1500);
    expect($tax->amountMinorUnits)->toBe(1500);

    // 8.25% tax = 825 basis points -> $8.25
    $taxFractional = $subtotal->multiplyByBasisPoints(825);
    expect($taxFractional->amountMinorUnits)->toBe(825);
});

test('money allocates across integer ratios without losing cents', function (): void {
    $total = Money::of(10000, 'USD'); // $100.00
    $shares = $total->allocate([1, 1, 1]);

    expect($shares)->toHaveCount(3)
        ->and($shares[0]->amountMinorUnits)->toBe(3334)
        ->and($shares[1]->amountMinorUnits)->toBe(3333)
        ->and($shares[2]->amountMinorUnits)->toBe(3333);

    $allocatedSum = $shares[0]->add($shares[1])->add($shares[2]);
    expect($allocatedSum->amountMinorUnits)->toBe(10000);
});

test('money comparison predicates operate accurately', function (): void {
    $ten = Money::of(1000, 'USD');
    $twenty = Money::of(2000, 'USD');
    $zero = Money::zero('USD');

    expect($twenty->isGreaterThan($ten))->toBeTrue()
        ->and($ten->isLessThan($twenty))->toBeTrue()
        ->and($ten->isGreaterThanOrEqualTo($ten))->toBeTrue()
        ->and($zero->isZero())->toBeTrue()
        ->and($ten->isPositive())->toBeTrue();
});

test('money serializes to json cleanly', function (): void {
    $money = Money::of(4500, 'USD');
    $json = json_encode($money);

    expect($json)->toBe('{"amount_minor_units":4500,"currency":"USD","formatted":"$45.00"}');
});
