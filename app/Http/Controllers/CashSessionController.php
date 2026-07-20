<?php

namespace App\Http\Controllers;

use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Http\Requests\CashSessions\CloseCashSessionRequest;
use App\Http\Requests\CashSessions\OpenCashSessionRequest;
use App\Http\Requests\CashSessions\StoreCashMovementRequest;
use App\Models\Branch;
use App\Models\BusinessMembership;
use App\Models\CashSession;
use App\Services\CashSessionService;
use App\Services\StaffShiftService;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use App\Support\Time\BusinessClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CashSessionController extends Controller
{
    public function index(Request $request, TenantContext $tenant): Response
    {
        $this->authorize('viewAny', CashSession::class);

        $business = $tenant->business();
        abort_unless($business, 403);

        $branchId = $request->integer('branch_id') ?: null;
        $userId = $request->integer('user_id') ?: null;
        $status = $request->query('status');
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $hasVariance = $request->query('has_variance');

        $timezone = BusinessClock::resolve($business->timezone, $business->country);
        $currency = $business->currency;

        $sessions = CashSession::query()
            ->forBusiness($business)
            ->with(['user:id,name,email', 'branch:id,name', 'closedByUser:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(
                is_string($status) && in_array($status, CashSessionStatus::values(), true),
                fn ($q) => $q->where('status', $status),
            )
            ->when($dateFrom !== '', function ($q) use ($dateFrom, $timezone): void {
                $q->where('opened_at', '>=', BusinessClock::startOfDayUtc($dateFrom, $timezone));
            })
            ->when($dateTo !== '', function ($q) use ($dateTo, $timezone): void {
                $q->where('opened_at', '<=', BusinessClock::endOfDayUtc($dateTo, $timezone));
            })
            ->when($hasVariance === '1', fn ($q) => $q->where('variance', '!=', 0))
            ->when($hasVariance === '0', fn ($q) => $q->where(fn ($q) => $q->whereNull('variance')->orWhere('variance', 0)))
            ->orderByDesc('opened_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (CashSession $session) => $this->listPayload($session, $currency, $timezone));

        return Inertia::render('cash-sessions/index', [
            'sessions' => $sessions,
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
                ])
                ->values(),
            'statuses' => collect(CashSessionStatus::cases())->map(fn (CashSessionStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values(),
            'filters' => [
                'branch_id' => $branchId,
                'user_id' => $userId,
                'status' => is_string($status) && in_array($status, CashSessionStatus::values(), true) ? $status : null,
                'date_from' => $dateFrom !== '' ? $dateFrom : null,
                'date_to' => $dateTo !== '' ? $dateTo : null,
                'has_variance' => in_array($hasVariance, ['0', '1'], true) ? $hasVariance : null,
            ],
            'timezone' => $timezone,
            'currency' => $currency,
        ]);
    }

    public function show(
        CashSession $cashSession,
        TenantContext $tenant,
        CashSessionService $cash,
    ): Response {
        $this->authorize('view', $cashSession);

        $business = $tenant->business();
        abort_unless($business, 403);

        $cashSession->load([
            'user:id,name,email',
            'branch:id,name',
            'closedByUser:id,name',
            'varianceApprover:id,name',
            'staffShift',
            'movements' => fn ($q) => $q->with('recorder:id,name')->orderByDesc('created_at'),
        ]);

        $timezone = BusinessClock::resolve($business->timezone, $business->country);
        $currency = $business->currency;
        $summary = $cashSession->status->isOpen()
            ? $cash->liveSummary($cashSession)
            : ($cashSession->z_report_snapshot ?? []);

        return Inertia::render('cash-sessions/show', [
            'session' => $this->detailPayload($cashSession, $currency, $timezone),
            'summary' => $summary,
            'timezone' => $timezone,
            'currency' => $currency,
            'permissions' => [
                'record_movement' => $tenant->user()?->can('recordMovement', $cashSession) ?? false,
                'close' => $tenant->user()?->can('close', $cashSession) ?? false,
                'force_close' => $tenant->user()?->can('forceClose', $cashSession) ?? false,
            ],
        ]);
    }

    public function open(
        OpenCashSessionRequest $request,
        TenantContext $tenant,
        StaffShiftService $shifts,
        CashSessionService $cash,
    ): RedirectResponse {
        $this->authorize('open', CashSession::class);

        $business = $tenant->business();
        $branch = $tenant->branch();
        abort_unless($business && $branch, 403);

        $openShift = $shifts->openShiftFor($business, $request->user());

        if ($openShift === null || $openShift->branch_id !== $branch->id) {
            throw ValidationException::withMessages([
                'cash_session' => 'Clock in on this branch before opening the cash drawer.',
            ]);
        }

        $session = $cash->open(
            $openShift,
            (int) $request->validated('opening_float'),
            $request->validated('opening_notes'),
            $request->user(),
        );

        return redirect()
            ->route('cash-sessions.show', $session)
            ->with('success', 'Cash drawer opened.');
    }

    public function storeMovement(
        StoreCashMovementRequest $request,
        CashSession $cashSession,
        CashSessionService $cash,
    ): RedirectResponse {
        $this->authorize('recordMovement', $cashSession);

        $cash->recordMovement(
            $cashSession,
            CashMovementType::from($request->validated('type')),
            (int) $request->validated('amount'),
            $request->validated('reason'),
            $request->validated('notes'),
            $request->user(),
        );

        return back()->with('success', 'Cash movement recorded.');
    }

    public function close(
        CloseCashSessionRequest $request,
        CashSession $cashSession,
        CashSessionService $cash,
    ): RedirectResponse {
        $this->authorize('close', $cashSession);

        $session = $cash->close(
            $cashSession,
            (int) $request->validated('counted_cash'),
            (int) $request->validated('closing_float_left'),
            $request->validated('variance_reason'),
            $request->validated('manager_approval'),
            $request->user(),
        );

        return redirect()
            ->route('cash-sessions.show', $session)
            ->with('success', 'Cash drawer closed and reconciled.');
    }

    public function zReport(
        CashSession $cashSession,
        TenantContext $tenant,
    ): HttpResponse {
        $this->authorize('view', $cashSession);

        $business = $tenant->business();
        abort_unless($business, 403);

        if ($cashSession->status->isOpen()) {
            abort(404);
        }

        $snapshot = $cashSession->z_report_snapshot ?? [];
        $cashSession->load(['user:id,name', 'branch:id,name', 'varianceApprover:id,name']);

        return response()
            ->view('cash-sessions.z-report', [
                'session' => $cashSession,
                'report' => $snapshot,
                'business' => $business,
                'labels' => trans('kospal.cash'),
            ])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    protected function listPayload(CashSession $session, string $currency, string $timezone): array
    {
        return [
            'id' => $session->id,
            'user_name' => $session->user?->name,
            'branch_name' => $session->branch?->name,
            'status' => $session->status->value,
            'status_label' => $session->status->label(),
            'opened_at' => $session->opened_at?->timezone($timezone)->toIso8601String(),
            'closed_at' => $session->closed_at?->timezone($timezone)->toIso8601String(),
            'opening_float_minor' => $session->opening_float,
            'opening_float_formatted' => Money::format($session->opening_float, $currency),
            'expected_cash_formatted' => $session->expected_cash !== null
                ? Money::format($session->expected_cash, $currency)
                : null,
            'counted_cash_formatted' => $session->counted_cash !== null
                ? Money::format($session->counted_cash, $currency)
                : null,
            'variance_minor' => $session->variance,
            'variance_formatted' => $session->variance !== null
                ? Money::format($session->variance, $currency)
                : null,
            'has_variance' => $session->variance !== null && $session->variance !== 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function detailPayload(CashSession $session, string $currency, string $timezone): array
    {
        return [
            ...$this->listPayload($session, $currency, $timezone),
            'user_email' => $session->user?->email,
            'opening_notes' => $session->opening_notes,
            'expected_cash_minor' => $session->expected_cash,
            'expected_cash_formatted' => $session->expected_cash !== null
                ? Money::format($session->expected_cash, $currency)
                : null,
            'counted_cash_minor' => $session->counted_cash,
            'closing_float_left_minor' => $session->closing_float_left,
            'closing_float_left_formatted' => $session->closing_float_left !== null
                ? Money::format($session->closing_float_left, $currency)
                : null,
            'variance_reason' => $session->variance_reason,
            'variance_approver_name' => $session->varianceApprover?->name,
            'closed_by_name' => $session->closedByUser?->name,
            'staff_shift_id' => $session->staff_shift_id,
            'is_open' => $session->status->isOpen(),
            'movements' => $session->movements->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type->value,
                'type_label' => $m->type->label(),
                'amount_minor' => $m->amount,
                'amount_formatted' => Money::format($m->amount, $currency),
                'reason' => $m->reason,
                'notes' => $m->notes,
                'recorded_by_name' => $m->recorder?->name,
                'created_at' => $m->created_at?->timezone($timezone)->toIso8601String(),
            ])->values(),
        ];
    }
}
