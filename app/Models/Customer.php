<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'phone',
        'email',
        'address',
        'notes',
        'is_active',
        'credit_enabled',
        'credit_limit',
        'payment_terms_days',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'credit_enabled' => 'boolean',
            'credit_limit' => 'integer',
            'payment_terms_days' => 'integer',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CustomerLedgerEntry::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($term)).'%';

        return $query->where(function (Builder $builder) use ($like): void {
            $builder->where('name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like);
        });
    }
}
