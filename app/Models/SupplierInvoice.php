<?php

namespace App\Models;

use App\Enums\SupplierInvoiceStatus;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\SupplierInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SupplierInvoice extends Model
{
    /** @use HasFactory<SupplierInvoiceFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'reference',
        'supplier_invoice_number',
        'supplier_id',
        'branch_id',
        'goods_received_note_id',
        'purchase_order_id',
        'status',
        'subtotal',
        'tax_total',
        'total',
        'amount_paid',
        'invoice_date',
        'due_date',
        'notes',
        'created_by',
        'posted_by',
        'posted_at',
        'voided_by',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupplierInvoiceStatus::class,
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function amountDue(): int
    {
        return max(0, $this->total - $this->amount_paid);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function goodsReceivedNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierInvoiceItem::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
