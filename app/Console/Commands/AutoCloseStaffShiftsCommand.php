<?php

namespace App\Console\Commands;

use App\Enums\OperatingMode;
use App\Enums\StaffShiftStatus;
use App\Models\Business;
use App\Models\StaffShift;
use App\Support\Audit\AuditLogger;
use App\Support\Time\BusinessClock;
use App\Support\Time\OperatingHours;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoCloseStaffShiftsCommand extends Command
{
    protected $signature = 'shifts:auto-close {--grace=60 : Minutes after store close before auto-closing}';

    protected $description = 'Auto-close open staff shifts for daytime stores past closing time';

    public function handle(AuditLogger $audit): int
    {
        $grace = max(0, (int) $this->option('grace'));
        $closed = 0;

        Business::query()
            ->where('is_active', true)
            ->with('branches')
            ->each(function (Business $business) use ($audit, $grace, &$closed): void {
                foreach ($business->branches as $branch) {
                    if (! $branch->is_active) {
                        continue;
                    }

                    $resolved = OperatingHours::resolve($business, $branch);

                    if ($resolved['mode'] !== OperatingMode::Daytime || $resolved['closes_at'] === null) {
                        continue;
                    }

                    $timezone = BusinessClock::resolve($business->timezone, $business->country);
                    $localNow = BusinessClock::now($timezone);
                    $closeToday = $localNow->setTimeFromTimeString($resolved['closes_at'])->addMinutes($grace);

                    if ($localNow->lt($closeToday)) {
                        continue;
                    }

                    $openShifts = StaffShift::query()
                        ->forBusiness($business)
                        ->where('branch_id', $branch->id)
                        ->where('status', StaffShiftStatus::Open)
                        ->where('clocked_in_at', '<', $closeToday->utc())
                        ->get();

                    foreach ($openShifts as $shift) {
                        DB::transaction(function () use ($shift, $audit, &$closed): void {
                            $locked = StaffShift::query()->whereKey($shift->id)->lockForUpdate()->first();
                            if ($locked === null || ! $locked->status->isOpen()) {
                                return;
                            }

                            $locked->update([
                                'status' => StaffShiftStatus::AutoClosed,
                                'clocked_out_at' => now(),
                                'close_reason' => 'Auto-closed after store closing time.',
                            ]);

                            $audit->log(
                                action: 'shift.auto_closed',
                                auditable: $locked,
                                businessId: $locked->business_id,
                            );

                            $closed++;
                        });
                    }
                }
            });

        $this->info("Auto-closed {$closed} shift(s).");

        return self::SUCCESS;
    }
}
