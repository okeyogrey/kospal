<?php

use App\Support\Analytics\AnalyticsFilter;
use App\Support\Analytics\DateGrouping;
use App\Support\Time\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\CreatesBusinesses;

uses(RefreshDatabase::class, CreatesBusinesses::class);

it('resolves Kenya and Burundi timezones', function () {
    expect(BusinessClock::resolve('Africa/Nairobi', 'KE'))->toBe('Africa/Nairobi')
        ->and(BusinessClock::resolve(null, 'BI'))->toBe('Africa/Bujumbura')
        ->and(BusinessClock::utcOffset('Africa/Nairobi'))->toMatch('/^\+\d{2}:\d{2}$/');
});

it('converts business calendar dates to UTC bounds for Kenya', function () {
    $from = BusinessClock::startOfDayUtc('2026-07-15', 'Africa/Nairobi');
    $to = BusinessClock::endOfDayUtc('2026-07-15', 'Africa/Nairobi');

    expect($from->timezone->getName())->toBe('UTC')
        ->and($from->toDateTimeString())->toBe('2026-07-14 21:00:00')
        ->and($to->toDateTimeString())->toBe('2026-07-15 20:59:59');
});

it('builds analytics filters using the business timezone', function () {
    ['business' => $business, 'branch' => $branch] = $this->createBusinessWithOwner([
        'timezone' => 'Africa/Bujumbura',
        'country' => 'BI',
        'currency' => 'BIF',
    ]);

    $request = Request::create('/reports', 'GET', [
        'date_from' => '2026-07-15',
        'date_to' => '2026-07-15',
    ]);

    $filter = AnalyticsFilter::fromRequest($request, $business, [(int) $branch->id]);

    expect($filter->timezone())->toBe('Africa/Bujumbura')
        ->and($filter->toArray()['date_from'])->toBe('2026-07-15')
        ->and($filter->toArray()['date_to'])->toBe('2026-07-15')
        ->and($filter->dateFrom->lessThan($filter->dateTo))->toBeTrue();
});

it('groups SQL periods in the business timezone', function () {
    $expression = DateGrouping::expression('sales.created_at', 'day', 'Africa/Nairobi');

    expect($expression)->not->toBe('DATE(sales.created_at)');
});

it('computes local day boundaries consistently with CarbonImmutable', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-15 02:30:00', 'UTC'));

    $now = BusinessClock::now('Africa/Nairobi');

    expect($now->toDateString())->toBe('2026-07-15')
        ->and(BusinessClock::startOfMonthUtc($now, 'Africa/Nairobi')->toDateTimeString())
        ->toBe('2026-06-30 21:00:00');

    CarbonImmutable::setTestNow();
});
