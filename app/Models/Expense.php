<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'branch_id',
        'expense_category_id',
        'created_by',
        'expense_date',
        'currency',
        'amount',
        'payee',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function receipt(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')->latestOfMany();
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        $like = '%'.$search.'%';

        return $query->where(function (Builder $builder) use ($like): void {
            $builder->where('payee', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }
}
