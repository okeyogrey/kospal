<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'branch_id',
        'customer_id',
        'cashier_id',
        'staff_shift_id',
        'cash_session_id',
        'approved_by',
        'sale_number',
        'status',
        'payment_method',
        'currency',
        'subtotal',
        'discount_amount',
        'total',
        'amount_paid',
        'cash_tendered',
        'change_given',
        'customer_name',
        'notes',
        'client_request_id',
        'voided_by',
        'voided_at',
        'void_reason',
        'held_at',
        'held_label',
        'resumed_from_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'payment_method' => PaymentMethod::class,
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'cash_tendered' => 'integer',
            'change_given' => 'integer',
            'voided_at' => 'datetime',
            'held_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function staffShift(): BelongsTo
    {
        return $this->belongsTo(StaffShift::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function resumedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resumed_from_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
    }

    public function amountDue(): int
    {
        return max(0, $this->total - $this->amount_paid);
    }

    public function isOpenForPayment(): bool
    {
        return $this->status === SaleStatus::Completed && $this->amountDue() > 0;
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
