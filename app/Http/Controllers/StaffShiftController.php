<?php

namespace App\Http\Controllers;

use App\Enums\StaffShiftStatus;
use App\Http\Requests\Shifts\ClockInShiftRequest;
use App\Http\Requests\Shifts\ClockOutShiftRequest;
use App\Http\Requests\Shifts\ForceCloseShiftRequest;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\StaffShift;
use App\Services\StaffShiftService;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use App\Support\Time\BusinessClock;
use App\Support\Time\OperatingHours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffShiftController extends Controller
{
    public function index(Request $request, TenantContext $tenant): Response
    {
        $this->authorize('viewAny', StaffShift::class);

        $business = $tenant->business();
        abort_unless($business, 403);

        $branchId = $request->integer('branch_id') ?: null;
        $userId = $request->integer('user_id') ?: null;
        $status = $request->query('status');
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $timezone = BusinessClock::resolve($business->timezone, $business->country);

        $shifts = StaffShift::query()
            ->forBusiness($business)
            ->with(['user:id,name,email', 'branch:id,name', 'closedBy:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(
                is_string($status) && in_array($status, StaffShiftStatus::values(), true),
                fn ($q) => $q->where('status', $status),
            )
            ->when($dateFrom !== '', function ($q) use ($dateFrom, $timezone): void {
                $q->where('clocked_in_at', '>=', BusinessClock::startOfDayUtc($dateFrom, $timezone));
            })
            ->when($dateTo !== '', function ($q) use ($dateTo, $timezone): void {
                $q->where('clocked_in_at', '<=', BusinessClock::endOfDayUtc($dateTo, $timezone));
            })
            ->orderByDesc('clocked_in_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (StaffShift $shift) => $this->shiftPayload($shift, $timezone));

        return Inertia::render('shifts/index', [
            'shifts' => $shifts,
            'branches' => Branch::query()
                ->forBusiness($business)
                ->orderBy('name')
                ->get(['id', 'name']),
            'staff' => BusinessMembership::query()
                ->forBusiness($business)
                ->where('is_active', true)
                ->with('user:id,name,email')
                ->get()
                ->map(fn (BusinessMembership $m) => [
                    'id' => $m->user_id,
                    'name' => $m->user?->name,
                    'email' => $m->user?->email,
                    'role' => $m->role->value,
                ])
                ->values(),
            'statuses' => collect(StaffShiftStatus::cases())->map(fn (StaffShiftStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values(),
            'filters' => [
                'branch_id' => $branchId,
                'user_id' => $userId,
                'status' => is_string($status) && in_array($status, StaffShiftStatus::values(), true) ? $status : null,
                'date_from' => $dateFrom !== '' ? $dateFrom : null,
                'date_to' => $dateTo !== '' ? $dateTo : null,
            ],
            'timezone' => $timezone,
        ]);
    }

    public function show(StaffShift $shift, TenantContext $tenant): Response
    {
        $this->authorize('view', $shift);

        $business = $tenant->business();
        abort_unless($business, 403);

        $shift->load(['user:id,name,email', 'branch:id,name', 'closedBy:id,name', 'cashSession']);
        $timezone = BusinessClock::resolve($business->timezone, $business->country);

        return Inertia::render('shifts/show', [
            'shift' => $this->shiftPayload($shift, $timezone),
            'cash_session' => $shift->cashSession ? [
                'id' => $shift->cashSession->id,
                'status' => $shift->cashSession->status->value,
                'status_label' => $shift->cashSession->status->label(),
                'opening_float_formatted' => Money::format($shift->cashSession->opening_float, $business->currency),
                'variance_formatted' => $shift->cashSession->variance !== null
                    ? Money::format($shift->cashSession->variance, $business->currency)
                    : null,
                'has_variance' => $shift->cashSession->variance !== null && $shift->cashSession->variance !== 0,
            ] : null,
            'timezone' => $timezone,
            'permissions' => [
                'force_close' => $tenant->user()?->can('forceClose', $shift) ?? false,
            ],
        ]);
    }

    public function clockIn(
        ClockInShiftRequest $request,
        TenantContext $tenant,
        StaffShiftService $shifts,
    ): RedirectResponse {
        $business = $tenant->business();
        $branch = $tenant->branch();
        $role = $tenant->role();
        abort_unless($business && $branch && $role, 403);

        $result = $shifts->clockIn($business, $branch, $request->user(), $role);

        $message = 'Clocked in.';
        if ($result['outside_hours']) {
            $message .= ' Note: this is outside the store daytime hours.';
        }

        return back()->with('success', $message);
    }

    public function clockOut(
        ClockOutShiftRequest $request,
        TenantContext $tenant,
        StaffShiftService $shifts,
    ): RedirectResponse {
        $business = $tenant->business();
        abort_unless($business, 403);

        $open = $shifts->openShiftFor($business, $request->user());

        if ($open === null) {
            throw ValidationException::withMessages([
                'shift' => 'You do not have an open shift to clock out of.',
            ]);
        }

        $this->authorize('clockOut', $open);
        $shifts->clockOut($open, $request->user());

        return back()->with('success', 'Clocked out.');
    }

    public function forceClose(
        ForceCloseShiftRequest $request,
        StaffShift $shift,
        StaffShiftService $shifts,
    ): RedirectResponse {
        $shifts->forceClose($shift, $request->user(), $request->validated('close_reason'));

        return back()->with('success', 'Shift force-closed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function shiftPayload(StaffShift $shift, string $timezone): array
    {
        $duration = $shift->durationSeconds();

        return [
            'id' => $shift->id,
            'user_id' => $shift->user_id,
            'user_name' => $shift->user?->name,
            'user_email' => $shift->user?->email,
            'branch_id' => $shift->branch_id,
            'branch_name' => $shift->branch?->name,
            'role_at_clock_in' => $shift->role_at_clock_in->value,
            'status' => $shift->status->value,
            'status_label' => $shift->status->label(),
            'clocked_in_at' => $shift->clocked_in_at?->timezone($timezone)->toIso8601String(),
            'clocked_out_at' => $shift->clocked_out_at?->timezone($timezone)->toIso8601String(),
            'clocked_out_by_name' => $shift->closedBy?->name,
            'close_reason' => $shift->close_reason,
            'notes' => $shift->notes,
            'duration_seconds' => $duration,
            'duration_label' => $duration !== null ? $this->formatDuration($duration) : null,
            'is_open' => $shift->status->isOpen(),
        ];
    }

    protected function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes);
        }

        return sprintf('%dm', $minutes);
    }
}
