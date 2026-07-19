<?php

namespace App\Services;

use App\Enums\Plan;
use App\Enums\SubscriptionRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\SubscriptionRequest;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{requested_plan: string, transaction_code: string, notes?: string|null}  $data
     */
    public function submitRequest(Business $business, User $actor, array $data): SubscriptionRequest
    {
        $hasPending = SubscriptionRequest::query()
            ->forBusiness($business)
            ->where('status', SubscriptionRequestStatus::Pending)
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'requested_plan' => 'You already have a pending subscription request. Wait for review or contact support.',
            ]);
        }

        $subscriptionRequest = SubscriptionRequest::query()->create([
            'business_id' => $business->id,
            'requested_plan' => Plan::from($data['requested_plan']),
            'current_plan' => $business->plan,
            'status' => SubscriptionRequestStatus::Pending,
            'notes' => $data['notes'] ?? null,
            'transaction_code' => $data['transaction_code'],
            'requested_by_user_id' => $actor->id,
        ]);

        $this->audit->log(
            action: 'subscription.requested',
            auditable: $subscriptionRequest,
            metadata: [
                'requested_plan' => $subscriptionRequest->requested_plan->value,
                'transaction_code' => $subscriptionRequest->transaction_code,
            ],
            actor: $actor,
            businessId: $business->id,
        );

        return $subscriptionRequest;
    }

    /**
     * @param  array{
     *     plan?: string|null,
     *     subscription_status?: string|null,
     *     subscription_ends_at?: string|null,
     *     reviewer_notes?: string|null,
     * }  $data
     */
    public function approveRequest(SubscriptionRequest $request, User $reviewer, array $data): SubscriptionRequest
    {
        if ($request->status !== SubscriptionRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending subscription requests can be approved.',
            ]);
        }

        return DB::transaction(function () use ($request, $reviewer, $data) {
            $business = Business::query()->lockForUpdate()->findOrFail($request->business_id);
            $plan = Plan::from($data['plan'] ?? $request->requested_plan->value);
            $status = SubscriptionStatus::from($data['subscription_status'] ?? SubscriptionStatus::Active->value);
            $endsAt = $data['subscription_ends_at']
                ?? now()->addDays((int) config('kospal.default_subscription_days', 30))->toDateString();

            $previous = [
                'plan' => $business->plan->value,
                'subscription_status' => $business->subscription_status->value,
                'subscription_ends_at' => $business->subscription_ends_at?->toDateString(),
            ];

            $business->update([
                'plan' => $plan,
                'subscription_status' => $status,
                'subscription_ends_at' => $endsAt,
            ]);

            $request->update([
                'status' => SubscriptionRequestStatus::Approved,
                'reviewer_notes' => $data['reviewer_notes'] ?? null,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $this->audit->log(
                action: 'subscription.approved',
                auditable: $request->fresh(),
                metadata: [
                    'previous' => $previous,
                    'plan' => $plan->value,
                    'subscription_status' => $status->value,
                    'subscription_ends_at' => $endsAt,
                    'reviewer_notes' => $data['reviewer_notes'] ?? null,
                    'transaction_code' => $request->transaction_code,
                ],
                actor: $reviewer,
                businessId: $business->id,
            );

            return $request->fresh();
        });
    }

    /**
     * @param  array{reviewer_notes?: string|null}  $data
     */
    public function rejectRequest(SubscriptionRequest $request, User $reviewer, array $data): SubscriptionRequest
    {
        if ($request->status !== SubscriptionRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending subscription requests can be rejected.',
            ]);
        }

        $request->update([
            'status' => SubscriptionRequestStatus::Rejected,
            'reviewer_notes' => $data['reviewer_notes'] ?? null,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $this->audit->log(
            action: 'subscription.rejected',
            auditable: $request,
            metadata: [
                'requested_plan' => $request->requested_plan->value,
                'reviewer_notes' => $data['reviewer_notes'] ?? null,
                'transaction_code' => $request->transaction_code,
            ],
            actor: $reviewer,
            businessId: $request->business_id,
        );

        return $request->fresh();
    }

    /**
     * @param  array{
     *     plan: string,
     *     subscription_status: string,
     *     subscription_ends_at?: string|null,
     *     reviewer_notes?: string|null,
     * }  $data
     */
    public function updateBusinessSubscription(Business $business, User $actor, array $data): Business
    {
        $previous = [
            'plan' => $business->plan->value,
            'subscription_status' => $business->subscription_status->value,
            'subscription_ends_at' => $business->subscription_ends_at?->toDateString(),
        ];

        $business->update([
            'plan' => Plan::from($data['plan']),
            'subscription_status' => SubscriptionStatus::from($data['subscription_status']),
            'subscription_ends_at' => $data['subscription_ends_at'] ?? null,
        ]);

        $this->audit->log(
            action: 'subscription.updated',
            auditable: $business,
            metadata: [
                'previous' => $previous,
                'plan' => $data['plan'],
                'subscription_status' => $data['subscription_status'],
                'subscription_ends_at' => $data['subscription_ends_at'] ?? null,
                'reviewer_notes' => $data['reviewer_notes'] ?? null,
            ],
            actor: $actor,
            businessId: $business->id,
        );

        return $business->fresh();
    }

    public function expireIfPastDue(Business $business): Business
    {
        if (
            $business->subscription_status === SubscriptionStatus::Active
            && $business->subscription_ends_at !== null
            && $business->subscription_ends_at->isPast()
        ) {
            $endsAt = $business->subscription_ends_at->toIso8601String();

            $business->update([
                'subscription_status' => SubscriptionStatus::Expired,
            ]);

            $this->audit->log(
                action: 'subscription.expired',
                auditable: $business,
                metadata: [
                    'subscription_ends_at' => $endsAt,
                ],
                businessId: $business->id,
            );

            $business->refresh();
        }

        return $business;
    }
}
