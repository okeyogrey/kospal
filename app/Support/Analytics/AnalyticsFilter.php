<?php

namespace App\Support\Analytics;

use App\Models\Business;
use App\Support\Time\BusinessClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class AnalyticsFilter
{
    /**
     * @param  list<int>  $allowedBranchIds
     */
    public function __construct(
        public readonly Business $business,
        public readonly array $allowedBranchIds,
        public readonly ?int $branchId,
        public readonly CarbonInterface $dateFrom,
        public readonly CarbonInterface $dateTo,
        public readonly string $grain = 'day',
        public readonly bool $includeVoided = false,
    ) {
        if ($this->branchId !== null && ! in_array($this->branchId, $this->allowedBranchIds, true)) {
            throw new InvalidArgumentException('Branch is outside the allowed set.');
        }
    }

    /**
     * @param  list<int>  $allowedBranchIds
     */
    public static function fromRequest(
        Request $request,
        Business $business,
        array $allowedBranchIds,
        ?CarbonInterface $defaultFrom = null,
        ?CarbonInterface $defaultTo = null,
    ): self {
        $timezone = BusinessClock::resolve($business->timezone, $business->country);
        $branchId = $request->integer('branch_id') ?: null;

        if ($branchId !== null && ! in_array($branchId, $allowedBranchIds, true)) {
            abort(403);
        }

        $dateFromRaw = $request->query('date_from');
        $dateToRaw = $request->query('date_to');

        $nowLocal = BusinessClock::now($timezone);

        if (is_string($dateFromRaw) && $dateFromRaw !== '') {
            $dateFrom = BusinessClock::startOfDayUtc($dateFromRaw, $timezone);
        } elseif ($defaultFrom !== null) {
            $dateFrom = CarbonImmutable::instance($defaultFrom)->utc();
        } else {
            $dateFrom = $nowLocal->startOfMonth()->startOfDay()->utc();
        }

        if (is_string($dateToRaw) && $dateToRaw !== '') {
            $dateTo = BusinessClock::endOfDayUtc($dateToRaw, $timezone);
        } elseif ($defaultTo !== null) {
            $dateTo = CarbonImmutable::instance($defaultTo)->utc();
        } else {
            $dateTo = $nowLocal->endOfDay()->utc();
        }

        if ($dateFrom->greaterThan($dateTo)) {
            $fromLocal = CarbonImmutable::instance($dateFrom)->timezone($timezone)->toDateString();
            $toLocal = CarbonImmutable::instance($dateTo)->timezone($timezone)->toDateString();
            $dateFrom = BusinessClock::startOfDayUtc($toLocal, $timezone);
            $dateTo = BusinessClock::endOfDayUtc($fromLocal, $timezone);
        }

        $grain = (string) $request->query('grain', 'day');
        if (! in_array($grain, ['day', 'week', 'month'], true)) {
            $grain = 'day';
        }

        $includeVoided = $request->boolean('include_voided')
            && $business->plan->allowsEnhancedExports();

        return new self(
            business: $business,
            allowedBranchIds: $allowedBranchIds,
            branchId: $branchId,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            grain: $grain,
            includeVoided: $includeVoided,
        );
    }

    /**
     * @return list<int>
     */
    public function branchIds(): array
    {
        if ($this->branchId !== null) {
            return [$this->branchId];
        }

        return $this->allowedBranchIds;
    }

    public function currency(): string
    {
        return $this->business->currency;
    }

    public function timezone(): string
    {
        return BusinessClock::resolve($this->business->timezone, $this->business->country);
    }

    /**
     * @return array{branch_id: int|null, date_from: string, date_to: string, grain: string, include_voided: bool, timezone: string}
     */
    public function toArray(): array
    {
        $timezone = $this->timezone();

        return [
            'branch_id' => $this->branchId,
            'date_from' => CarbonImmutable::instance($this->dateFrom)->timezone($timezone)->toDateString(),
            'date_to' => CarbonImmutable::instance($this->dateTo)->timezone($timezone)->toDateString(),
            'grain' => $this->grain,
            'include_voided' => $this->includeVoided,
            'timezone' => $timezone,
        ];
    }
}
