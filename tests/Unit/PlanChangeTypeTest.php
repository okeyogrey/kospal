<?php

use App\Enums\Plan;
use App\Enums\PlanChangeType;

it('ranks starter below pro below enterprise', function () {
    expect(Plan::Starter->rank())->toBe(1)
        ->and(Plan::Pro->rank())->toBe(2)
        ->and(Plan::Enterprise->rank())->toBe(3);
});

it('names upgrade renew and downgrade from the current edition', function (Plan $current, Plan $target, PlanChangeType $expected) {
    expect($current->changeTypeToward($target))->toBe($expected);
})->with([
    [Plan::Starter, Plan::Pro, PlanChangeType::Upgrade],
    [Plan::Starter, Plan::Enterprise, PlanChangeType::Upgrade],
    [Plan::Pro, Plan::Enterprise, PlanChangeType::Upgrade],
    [Plan::Pro, Plan::Pro, PlanChangeType::Renew],
    [Plan::Starter, Plan::Starter, PlanChangeType::Renew],
    [Plan::Enterprise, Plan::Pro, PlanChangeType::Downgrade],
    [Plan::Pro, Plan::Starter, PlanChangeType::Downgrade],
]);
