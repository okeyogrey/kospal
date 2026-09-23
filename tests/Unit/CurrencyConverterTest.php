<?php

use App\Support\Money\CurrencyConverter;
use App\Support\Money\Money;

it('converts KES to BIF at one shilling to fifty francs', function () {
    expect(CurrencyConverter::bifPerKes())->toBe(50)
        ->and(CurrencyConverter::kesMajorToBif(1))->toBe(50)
        ->and(CurrencyConverter::kesMajorToBif(2600))->toBe(130000)
        ->and(CurrencyConverter::kesMinorToBifMinor(Money::toMinor(2600, 'KES')))->toBe(130000);
});

it('converts BIF back to KES using the same fixed rate', function () {
    expect(CurrencyConverter::bifToKesMajor(130000))->toBe(2600.0)
        ->and(CurrencyConverter::convertMinor(260000, 'KES', 'BIF'))->toBe(130000);
});
