<?php

namespace App\Models;

use App\Enums\OperatingMode;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'code',
        'address',
        'city',
        'phone',
        'operating_mode',
        'opens_at',
        'closes_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'operating_mode' => OperatingMode::class,
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('business_id')
            ->withTimestamps();
    }

    public function staffShifts(): HasMany
    {
        return $this->hasMany(StaffShift::class);
    }
}
