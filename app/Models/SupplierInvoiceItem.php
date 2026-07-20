<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\SupplierInvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceItem extends Model
{
    /** @use HasFactory<SupplierInvoiceItemFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'supplier_invoice_id',
        'product_id',
        'description',
        'quantity',
        'unit_cost',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'integer',
            'line_total' => 'integer',
        ];
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
