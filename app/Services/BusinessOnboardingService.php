<?php

namespace App\Services;

use App\Enums\BusinessRole;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Time\BusinessClock;
use Illuminate\Support\Facades\DB;

class BusinessOnboardingService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     country: string,
     *     currency: string,
     *     timezone?: string,
     *     default_locale?: string,
     *     branch_name: string,
     *     branch_city?: string|null,
     *     branch_address?: string|null,
     *     branch_phone?: string|null,
     * }  $data
     * @return array{business: Business, branch: Branch, membership: BusinessMembership}
     */
    public function onboard(User $owner, array $data): array
    {
        return DB::transaction(function () use ($owner, $data) {
            $timezone = BusinessClock::resolve(
                $data['timezone'] ?? null,
                $data['country'],
            );
            $defaultLocale = $data['default_locale']
                ?? BusinessClock::localeForCountry($data['country']);

            $business = Business::query()->create([
                'name' => $data['name'],
                'country' => $data['country'],
                'currency' => $data['currency'],
                'timezone' => $timezone,
                'default_locale' => $defaultLocale,
                'plan' => Plan::from(config('kospal.default_plan')),
                'subscription_status' => SubscriptionStatus::Pending,
                'owner_user_id' => $owner->id,
                'is_active' => true,
            ]);

            if ($owner->preferred_locale === null) {
                $owner->preferred_locale = $defaultLocale;
            }

            $branch = Branch::query()->create([
                'business_id' => $business->id,
                'name' => $data['branch_name'],
                'city' => $data['branch_city'] ?? null,
                'address' => $data['branch_address'] ?? null,
                'phone' => $data['branch_phone'] ?? null,
                'is_active' => true,
            ]);

            $membership = BusinessMembership::query()->create([
                'business_id' => $business->id,
                'user_id' => $owner->id,
                'role' => BusinessRole::Owner,
                'is_active' => true,
                'joined_at' => now(),
            ]);

            $owner->forceFill([
                'current_business_id' => $business->id,
                'current_branch_id' => $branch->id,
                'preferred_locale' => $owner->preferred_locale ?? $defaultLocale,
            ])->save();

            if (session()->isStarted()) {
                session()->put('locale', $owner->preferred_locale);
            }

            $this->audit->log(
                action: 'business.onboarded',
                auditable: $business,
                metadata: [
                    'branch_id' => $branch->id,
                ],
                actor: $owner,
                businessId: $business->id,
            );

            return compact('business', 'branch', 'membership');
        });
    }
}
