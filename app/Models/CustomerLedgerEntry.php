<?php

namespace App\Models;

use App\Enums\CustomerLedgerEntryType;
use App\Enums\LedgerDirection;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\CustomerLedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CustomerLedgerEntry extends Model
{
    /** @use HasFactory<CustomerLedgerEntryFactory> */
    use BelongsToBusiness, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'business_id',
        'customer_id',
        'type',
        'direction',
        'amount',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'entry_date',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerLedgerEntryType::class,
            'direction' => LedgerDirection::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
            'entry_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signedAmount(): int
    {
        return $this->direction->signedAmount($this->amount);
    }
}
