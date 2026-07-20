<?php

namespace App\Models;

use App\Enums\LicenseActivationMode;
use App\Enums\OperatingMode;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'country',
        'currency',
        'timezone',
        'operating_mode',
        'opens_at',
        'closes_at',
        'default_locale',
        'plan',
        'subscription_status',
        'subscription_ends_at',
        'license_activation_mode',
        'license_key',
        'license_id',
        'licensed_machine_id',
        'licensed_at',
        'max_staff_override',
        'owner_user_id',
        'is_active',
        'cashiers_can_log_expenses',
    ];

    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
            'subscription_status' => SubscriptionStatus::class,
            'operating_mode' => OperatingMode::class,
            'subscription_ends_at' => 'datetime',
            'license_activation_mode' => LicenseActivationMode::class,
            'license_key' => 'encrypted',
            'licensed_at' => 'datetime',
            'is_active' => 'boolean',
            'cashiers_can_log_expenses' => 'boolean',
            'max_staff_override' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Business $business): void {
            if (blank($business->slug)) {
                $business->slug = static::uniqueSlugFor($business->name);
            }
        });
    }

    public static function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'business';
        $slug = $base;
        $i = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessMembership::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function subscriptionRequests(): HasMany
    {
        return $this->hasMany(SubscriptionRequest::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function staffShifts(): HasMany
    {
        return $this->hasMany(StaffShift::class);
    }

    public function allowsWriteAccess(): bool
    {
        return $this->is_active && $this->subscription_status->allowsWriteAccess();
    }
}
