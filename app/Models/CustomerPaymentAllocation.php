<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\CustomerPaymentAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPaymentAllocation extends Model
{
    /** @use HasFactory<CustomerPaymentAllocationFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'customer_payment_id',
        'sale_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
