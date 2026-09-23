<?php

use App\Enums\Plan;
use App\Support\Plans\PlanPricing;

it('lists monthly plan prices in each currency', function () {
    expect(PlanPricing::monthlyMajor(Plan::Starter, 'USD'))->toBe(20)
        ->and(PlanPricing::monthlyMajor(Plan::Starter, 'KES'))->toBe(2600)
        ->and(PlanPricing::monthlyMajor(Plan::Starter, 'BIF'))->toBe(130000)
        ->and(PlanPricing::monthlyMajor(Plan::Pro, 'USD'))->toBe(40)
        ->and(PlanPricing::monthlyMajor(Plan::Pro, 'KES'))->toBe(5200)
        ->and(PlanPricing::monthlyMajor(Plan::Pro, 'BIF'))->toBe(260000)
        ->and(PlanPricing::monthlyMajor(Plan::Enterprise, 'USD'))->toBe(60)
        ->and(PlanPricing::monthlyMajor(Plan::Enterprise, 'KES'))->toBe(7800)
        ->and(PlanPricing::monthlyMajor(Plan::Enterprise, 'BIF'))->toBe(390000);
});

it('applies a stacked referral discount to the amount due', function () {
    $quote = PlanPricing::quote(Plan::Pro, 'KES', 20);

    expect($quote['list_minor'])->toBe(520000)
        ->and($quote['discount_percent'])->toBe(20)
        ->and($quote['due_minor'])->toBe(416000);
});
