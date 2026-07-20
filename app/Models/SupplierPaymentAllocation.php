<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\SupplierPaymentAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPaymentAllocation extends Model
{
    /** @use HasFactory<SupplierPaymentAllocationFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'supplier_payment_id',
        'supplier_invoice_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }
}
