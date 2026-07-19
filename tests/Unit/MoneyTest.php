<?php

use App\Support\Money\Money;

it('converts KES major units to minor units', function () {
    expect(Money::toMinor('10.50', 'KES'))->toBe(1050)
        ->and(Money::fromMinor(1050, 'KES'))->toBe('10.50');
});

it('converts BIF without fraction digits', function () {
    expect(Money::toMinor('1500', 'BIF'))->toBe(1500)
        ->and(Money::fromMinor(1500, 'BIF'))->toBe('1500')
        ->and(Money::exponent('BIF'))->toBe(0);
});

it('converts USD with two fraction digits', function () {
    expect(Money::toMinor('12.34', 'USD'))->toBe(1234)
        ->and(Money::fromMinor(1234, 'USD'))->toBe('12.34');
});

it('formats currency amounts for KES BIF and USD', function () {
    expect(Money::format(1050, 'KES'))->toContain('10.50')
        ->and(Money::format(1500, 'BIF'))->toContain('1')
        ->and(Money::format(250, 'USD'))->toContain('2.50');
});

it('rejects unsupported currencies', function () {
    Money::toMinor('1', 'EUR');
})->throws(InvalidArgumentException::class);
