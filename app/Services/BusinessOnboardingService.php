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
use App\Support\Deployment;
use App\Support\Time\BusinessClock;
use Illuminate\Support\Facades\DB;

class BusinessOnboardingService
{
    public function __construct(
        protected AuditLogger $audit,
        protected LicenseService $licenses,
        protected ReferralService $referrals,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     country: string,
     *     currency: string,
     *     timezone?: string,
     *     default_locale?: string,
     *     trial_edition?: string|null,
     *     branch_name: string,
     *     branch_city?: string|null,
     *     branch_address?: string|null,
     *     branch_phone?: string|null,
     *     referral_code?: string|null,
     * }  $data
     * @return array{business: Business, branch: Branch, membership: BusinessMembership}
     */
    public function onboard(User $owner, array $data): array
    {
        $referralCode = $data['referral_code'] ?? $this->referrals->capturedCode();
        $this->referrals->assertRedeemable(is_string($referralCode) ? $referralCode : null, $owner);

        $result = DB::transaction(function () use ($owner, $data) {
            $timezone = BusinessClock::resolve(
                $data['timezone'] ?? null,
                $data['country'],
            );
            $defaultLocale = $data['default_locale']
                ?? BusinessClock::localeForCountry($data['country']);
            $plan = $this->initialPlan($data);

            $business = Business::query()->create([
                'name' => $data['name'],
                'country' => $data['country'],
                'currency' => $data['currency'],
                'timezone' => $timezone,
                'default_locale' => $defaultLocale,
                'plan' => $plan,
                'subscription_status' => Deployment::isDesktop()
                    ? SubscriptionStatus::Trial
                    : SubscriptionStatus::Pending,
                'subscription_ends_at' => null,
                'owner_user_id' => $owner->id,
                'is_active' => true,
            ]);

            if (Deployment::isDesktop()) {
                $this->licenses->startTrial($business, $owner, $plan);
                $business->refresh();
            }

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
                    'deployment_mode' => Deployment::mode(),
                    'license_edition' => $business->plan->value,
                    'license_status' => $business->subscription_status->value,
                ],
                actor: $owner,
                businessId: $business->id,
            );

            return compact('business', 'branch', 'membership');
        });

        $this->referrals->redeemForOnboarding(
            $result['business'],
            $owner,
            is_string($referralCode) ? $referralCode : null,
        );

        return $result;
    }

    /**
     * @param  array{trial_edition?: string|null}  $data
     */
    protected function initialPlan(array $data): Plan
    {
        if (! Deployment::isDesktop()) {
            return Plan::from(config('kospal.default_plan'));
        }

        $chosen = $data['trial_edition'] ?? null;

        if (is_string($chosen)) {
            $edition = Plan::tryFrom($chosen);

            if ($edition !== null) {
                return $edition;
            }
        }

        $fallback = (string) config('deployment.license.default_edition', Plan::Pro->value);

        return Plan::tryFrom($fallback) ?? Plan::Pro;
    }
}
