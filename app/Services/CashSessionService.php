<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StaffShiftStatus;
use App\Models\Business;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\StaffShift;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashSessionService
{
    public function __construct(
        protected AuditLogger $audit,
        protected ManagerApprovalService $approvals,
    ) {}

    public function open(
        StaffShift $shift,
        int $openingFloat,
        ?string $notes,
        User $actor,
    ): CashSession {
        if (! $shift->status->isOpen()) {
            throw ValidationException::withMessages([
                'cash_session' => 'Cash drawer can only be opened during an active shift.',
            ]);
        }

        if ($shift->user_id !== $actor->id) {
            throw ValidationException::withMessages([
                'cash_session' => 'You can only open the cash drawer on your own shift.',
            ]);
        }

        if ($openingFloat < 0) {
            throw ValidationException::withMessages([
                'opening_float' => 'Opening float cannot be negative.',
            ]);
        }

        return DB::transaction(function () use ($shift, $openingFloat, $notes, $actor) {
            $existing = CashSession::query()
                ->where('staff_shift_id', $shift->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->status->isOpen()) {
                    throw ValidationException::withMessages([
                        'cash_session' => 'Cash drawer is already open for this shift.',
                    ]);
                }

                throw ValidationException::withMessages([
                    'cash_session' => 'A cash session already exists for this shift.',
                ]);
            }

            $session = CashSession::query()->create([
                'business_id' => $shift->business_id,
                'branch_id' => $shift->branch_id,
                'staff_shift_id' => $shift->id,
                'user_id' => $actor->id,
                'status' => CashSessionStatus::Open,
                'opening_float' => $openingFloat,
                'opening_notes' => $notes,
                'opened_at' => now(),
            ]);

            $this->audit->log(
                action: 'cash_session.opened',
                auditable: $session,
                metadata: [
                    'opening_float' => $openingFloat,
                    'branch_id' => $shift->branch_id,
                    'staff_shift_id' => $shift->id,
                ],
                actor: $actor,
                businessId: $shift->business_id,
            );

            return $session->load(['branch:id,name', 'user:id,name', 'staffShift']);
        });
    }

    public function recordMovement(
        CashSession $session,
        CashMovementType $type,
        int $amount,
        ?string $reason,
        ?string $notes,
        User $actor,
    ): CashMovement {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($session, $type, $amount, $reason, $notes, $actor) {
            $locked = CashSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->status->isOpen()) {
                throw ValidationException::withMessages([
                    'cash_session' => 'Cash drawer is closed.',
                ]);
            }

            if ($locked->user_id !== $actor->id) {
                throw ValidationException::withMessages([
                    'cash_session' => 'You can only record movements on your own cash session.',
                ]);
            }

            $movement = CashMovement::query()->create([
                'business_id' => $locked->business_id,
                'cash_session_id' => $locked->id,
                'type' => $type,
                'amount' => $amount,
                'reason' => $reason,
                'notes' => $notes,
                'recorded_by' => $actor->id,
            ]);

            $this->audit->log(
                action: 'cash_session.movement_recorded',
                auditable: $movement,
                metadata: [
                    'type' => $type->value,
                    'amount' => $amount,
                    'cash_session_id' => $locked->id,
                ],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $movement;
        });
    }

    /**
     * @param  array{pin?: string|null, login?: string|null, password?: string|null}|null  $approval
     */
    public function close(
        CashSession $session,
        int $countedCash,
        int $closingFloatLeft,
        ?string $varianceReason,
        ?array $approval,
        User $actor,
    ): CashSession {
        if ($countedCash < 0) {
            throw ValidationException::withMessages([
                'counted_cash' => 'Counted cash cannot be negative.',
            ]);
        }

        if ($closingFloatLeft < 0) {
            throw ValidationException::withMessages([
                'closing_float_left' => 'Closing float cannot be negative.',
            ]);
        }

        if ($closingFloatLeft > $countedCash) {
            throw ValidationException::withMessages([
                'closing_float_left' => 'Closing float cannot exceed the counted cash.',
            ]);
        }

        return DB::transaction(function () use ($session, $countedCash, $closingFloatLeft, $varianceReason, $approval, $actor) {
            $locked = CashSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->status->isOpen()) {
                throw ValidationException::withMessages([
                    'cash_session' => 'Cash drawer is already closed.',
                ]);
            }

            if ($locked->user_id !== $actor->id) {
                throw ValidationException::withMessages([
                    'cash_session' => 'You can only close your own cash session.',
                ]);
            }

            $expected = $this->calculateExpectedCash($locked);
            $variance = $countedCash - $expected;
            $approver = null;

            if ($variance !== 0) {
                if (trim((string) $varianceReason) === '') {
                    throw ValidationException::withMessages([
                        'variance_reason' => 'Explain the cash variance before closing.',
                    ]);
                }

                $approver = $this->approvals->resolve(
                    $locked->business,
                    $actor,
                    true,
                    $approval,
                );
            }

            $snapshot = $this->buildZReportSnapshot($locked, $expected, $countedCash, $closingFloatLeft, $variance);

            $locked->update([
                'status' => CashSessionStatus::Closed,
                'expected_cash' => $expected,
                'counted_cash' => $countedCash,
                'closing_float_left' => $closingFloatLeft,
                'variance' => $variance,
                'variance_reason' => $variance !== 0 ? $varianceReason : null,
                'variance_approved_by' => $approver?->id,
                'closed_at' => now(),
                'closed_by' => $actor->id,
                'z_report_snapshot' => $snapshot,
            ]);

            $this->audit->log(
                action: 'cash_session.closed',
                auditable: $locked,
                metadata: [
                    'expected_cash' => $expected,
                    'counted_cash' => $countedCash,
                    'closing_float_left' => $closingFloatLeft,
                    'variance' => $variance,
                ],
                actor: $actor,
                businessId: $locked->business_id,
            );

            if ($variance !== 0) {
                $this->audit->log(
                    action: 'cash_session.variance_recorded',
                    auditable: $locked,
                    metadata: [
                        'expected_cash' => $expected,
                        'counted_cash' => $countedCash,
                        'variance' => $variance,
                        'variance_reason' => $varianceReason,
                        'approved_by' => $approver?->id,
                    ],
                    actor: $actor,
                    businessId: $locked->business_id,
                );
            }

            return $locked->refresh()->load([
                'branch:id,name',
                'user:id,name',
                'closedByUser:id,name',
                'varianceApprover:id,name',
                'staffShift',
            ]);
        });
    }

    public function forceClose(
        CashSession $session,
        User $actor,
        string $reason,
    ): CashSession {
        return DB::transaction(function () use ($session, $actor, $reason) {
            $locked = CashSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->status->isOpen()) {
                return $locked;
            }

            $expected = $this->calculateExpectedCash($locked);
            $snapshot = $this->buildZReportSnapshot($locked, $expected, 0, 0, -$expected);

            $locked->update([
                'status' => CashSessionStatus::Closed,
                'expected_cash' => $expected,
                'counted_cash' => 0,
                'closing_float_left' => 0,
                'variance' => -$expected,
                'variance_reason' => 'Force closed: '.$reason,
                'closed_at' => now(),
                'closed_by' => $actor->id,
                'z_report_snapshot' => array_merge($snapshot, [
                    'force_closed' => true,
                    'force_close_reason' => $reason,
                ]),
            ]);

            $this->audit->log(
                action: 'cash_session.force_closed',
                auditable: $locked,
                metadata: [
                    'reason' => $reason,
                    'expected_cash' => $expected,
                ],
                actor: $actor,
                businessId: $locked->business_id,
            );

            $this->audit->log(
                action: 'cash_session.variance_recorded',
                auditable: $locked,
                metadata: [
                    'expected_cash' => $expected,
                    'counted_cash' => 0,
                    'variance' => -$expected,
                    'variance_reason' => 'Force closed: '.$reason,
                    'force_closed' => true,
                ],
                actor: $actor,
                businessId: $locked->business_id,
            );

            return $locked->refresh();
        });
    }

    public function calculateExpectedCash(CashSession $session): int
    {
        $cashSales = (int) Payment::query()
            ->whereHas('sale', function ($query) use ($session): void {
                $query->where('cash_session_id', $session->id)
                    ->where('status', SaleStatus::Completed);
            })
            ->where('method', PaymentMethod::Cash)
            ->sum('amount');

        $paidIns = (int) CashMovement::query()
            ->where('cash_session_id', $session->id)
            ->where('type', CashMovementType::PaidIn)
            ->sum('amount');

        $drops = (int) CashMovement::query()
            ->where('cash_session_id', $session->id)
            ->where('type', CashMovementType::Drop)
            ->sum('amount');

        return $session->opening_float + $cashSales + $paidIns - $drops;
    }

    /**
     * @return array<string, mixed>
     */
    public function liveSummary(CashSession $session): array
    {
        $expected = $this->calculateExpectedCash($session);
        $salesStats = $this->salesStats($session);
        $paymentBreakdown = $this->paymentBreakdown($session);
        $movements = $this->movementTotals($session);

        return [
            'opening_float_minor' => $session->opening_float,
            'expected_cash_minor' => $expected,
            'cash_sales_minor' => $movements['cash_sales_minor'],
            'paid_ins_minor' => $movements['paid_ins_minor'],
            'drops_minor' => $movements['drops_minor'],
            'sales' => $salesStats,
            'payment_breakdown' => $paymentBreakdown,
        ];
    }

    public function openSessionFor(Business $business, User $user): ?CashSession
    {
        return CashSession::query()
            ->forBusiness($business)
            ->where('user_id', $user->id)
            ->where('status', CashSessionStatus::Open)
            ->with(['branch:id,name', 'staffShift'])
            ->first();
    }

    public function openSessionOnBranch(Business $business, User $user, int $branchId): ?CashSession
    {
        return CashSession::query()
            ->forBusiness($business)
            ->where('user_id', $user->id)
            ->where('branch_id', $branchId)
            ->where('status', CashSessionStatus::Open)
            ->first();
    }

    public function hasOpenSessionOnBranch(Business $business, User $user, int $branchId): bool
    {
        return CashSession::query()
            ->forBusiness($business)
            ->where('user_id', $user->id)
            ->where('branch_id', $branchId)
            ->where('status', CashSessionStatus::Open)
            ->exists();
    }

    /**
     * @return array{staff_shift_id: int|null, cash_session_id: int|null}
     */
    public function resolveSaleContext(Business $business, User $user, int $branchId): array
    {
        $shift = StaffShift::query()
            ->forBusiness($business)
            ->where('user_id', $user->id)
            ->where('branch_id', $branchId)
            ->where('status', StaffShiftStatus::Open)
            ->first();

        $session = $shift !== null
            ? CashSession::query()
                ->where('staff_shift_id', $shift->id)
                ->where('status', CashSessionStatus::Open)
                ->first()
            : null;

        return [
            'staff_shift_id' => $shift?->id,
            'cash_session_id' => $session?->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildZReportSnapshot(
        CashSession $session,
        int $expected,
        int $counted,
        int $closingFloatLeft,
        int $variance,
    ): array {
        $business = $session->business;
        $currency = $business?->currency ?? 'KES';
        $salesStats = $this->salesStats($session);
        $paymentBreakdown = $this->paymentBreakdown($session);
        $movements = $this->movementTotals($session);
        $voided = $this->voidedStats($session);
        $returns = $this->returnStats($session);

        return [
            'generated_at' => now()->toIso8601String(),
            'session_id' => $session->id,
            'branch' => $session->branch?->name,
            'cashier' => $session->user?->name,
            'opened_at' => $session->opened_at?->toIso8601String(),
            'closed_at' => now()->toIso8601String(),
            'opening_float_minor' => $session->opening_float,
            'opening_float_formatted' => Money::format($session->opening_float, $currency),
            'expected_cash_minor' => $expected,
            'expected_cash_formatted' => Money::format($expected, $currency),
            'counted_cash_minor' => $counted,
            'counted_cash_formatted' => Money::format($counted, $currency),
            'closing_float_left_minor' => $closingFloatLeft,
            'closing_float_left_formatted' => Money::format($closingFloatLeft, $currency),
            'cash_removed_minor' => max(0, $counted - $closingFloatLeft),
            'cash_removed_formatted' => Money::format(max(0, $counted - $closingFloatLeft), $currency),
            'variance_minor' => $variance,
            'variance_formatted' => Money::format($variance, $currency),
            'cash_sales_minor' => $movements['cash_sales_minor'],
            'paid_ins_minor' => $movements['paid_ins_minor'],
            'drops_minor' => $movements['drops_minor'],
            'sales' => $salesStats,
            'payment_breakdown' => $paymentBreakdown,
            'voided' => $voided,
            'returns' => $returns,
            'currency' => $currency,
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function salesStats(CashSession $session): array
    {
        $aggregate = Sale::query()
            ->where('cash_session_id', $session->id)
            ->where('status', SaleStatus::Completed)
            ->selectRaw('COUNT(*) as sale_count')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as subtotal_minor')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as discount_minor')
            ->selectRaw('COALESCE(SUM(total), 0) as total_minor')
            ->first();

        return [
            'sale_count' => (int) ($aggregate->sale_count ?? 0),
            'subtotal_minor' => (int) ($aggregate->subtotal_minor ?? 0),
            'discount_minor' => (int) ($aggregate->discount_minor ?? 0),
            'total_minor' => (int) ($aggregate->total_minor ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function paymentBreakdown(CashSession $session): array
    {
        $currency = $session->business?->currency ?? 'KES';

        return Payment::query()
            ->whereHas('sale', function ($query) use ($session): void {
                $query->where('cash_session_id', $session->id)
                    ->where('status', SaleStatus::Completed);
            })
            ->groupBy('method')
            ->orderByDesc(DB::raw('SUM(amount)'))
            ->get([
                'method',
                DB::raw('COUNT(*) as payment_count'),
                DB::raw('SUM(amount) as total_minor'),
            ])
            ->map(fn ($row) => [
                'method' => $row->method instanceof PaymentMethod ? $row->method->value : (string) $row->method,
                'method_label' => $row->method instanceof PaymentMethod ? $row->method->label() : (string) $row->method,
                'payment_count' => (int) $row->payment_count,
                'total_minor' => (int) $row->total_minor,
                'total_formatted' => Money::format((int) $row->total_minor, $currency),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{cash_sales_minor: int, paid_ins_minor: int, drops_minor: int}
     */
    protected function movementTotals(CashSession $session): array
    {
        $cashSales = (int) Payment::query()
            ->whereHas('sale', function ($query) use ($session): void {
                $query->where('cash_session_id', $session->id)
                    ->where('status', SaleStatus::Completed);
            })
            ->where('method', PaymentMethod::Cash)
            ->sum('amount');

        $paidIns = (int) CashMovement::query()
            ->where('cash_session_id', $session->id)
            ->where('type', CashMovementType::PaidIn)
            ->sum('amount');

        $drops = (int) CashMovement::query()
            ->where('cash_session_id', $session->id)
            ->where('type', CashMovementType::Drop)
            ->sum('amount');

        return [
            'cash_sales_minor' => $cashSales,
            'paid_ins_minor' => $paidIns,
            'drops_minor' => $drops,
        ];
    }

    /**
     * @return array{count: int, total_minor: int}
     */
    protected function voidedStats(CashSession $session): array
    {
        $aggregate = Sale::query()
            ->where('cash_session_id', $session->id)
            ->where('status', SaleStatus::Voided)
            ->selectRaw('COUNT(*) as void_count')
            ->selectRaw('COALESCE(SUM(total), 0) as total_minor')
            ->first();

        return [
            'count' => (int) ($aggregate->void_count ?? 0),
            'total_minor' => (int) ($aggregate->total_minor ?? 0),
        ];
    }

    /**
     * @return array{count: int, total_minor: int}
     */
    protected function returnStats(CashSession $session): array
    {
        $aggregate = Payment::query()
            ->whereHas('sale', function ($query) use ($session): void {
                $query->where('cash_session_id', $session->id);
            })
            ->where('amount', '<', 0)
            ->selectRaw('COUNT(*) as return_count')
            ->selectRaw('COALESCE(SUM(ABS(amount)), 0) as total_minor')
            ->first();

        return [
            'count' => (int) ($aggregate->return_count ?? 0),
            'total_minor' => (int) ($aggregate->total_minor ?? 0),
        ];
    }
}
