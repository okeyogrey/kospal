<?php

namespace App\Services\Sync;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\SyncAccount;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Copies a linked shop into the office database so platform admin and every
 * other signed-in computer read the same products, stock, and sales.
 */
class HubShopMaterializer
{
    public function __construct(
        protected SyncApplier $applier,
    ) {}

    public function ensureBusiness(SyncAccount $account): ?Business
    {
        return SyncContext::silence(function () use ($account): ?Business {
            $existing = $this->findBusiness($account);

            if ($existing !== null) {
                return $existing;
            }

            $email = strtolower(trim((string) $account->owner_email));

            if ($email === '') {
                return null;
            }

            $owner = User::query()->where('email', $email)->first();

            if ($owner === null) {
                $owner = new User;
                $owner->forceFill([
                    'name' => 'Shop owner',
                    'email' => $email,
                    'password' => Str::password(24),
                    'email_verified_at' => now(),
                    'is_platform_super_admin' => false,
                ])->save();
            }

            $edition = Plan::tryFrom((string) config('deployment.license.default_edition', Plan::Pro->value))
                ?? Plan::Pro;
            $trialDays = max(1, (int) config('deployment.license.trial_days', 60));

            $business = new Business;
            $business->forceFill([
                'name' => (string) $account->business_name,
                'public_uuid' => (string) $account->business_public_uuid,
                'country' => 'KE',
                'currency' => 'KES',
                'timezone' => 'Africa/Nairobi',
                'default_locale' => 'en',
                'plan' => $edition,
                'subscription_status' => SubscriptionStatus::Trial,
                'subscription_ends_at' => now()->addDays($trialDays),
                'owner_user_id' => $owner->id,
                'is_active' => true,
            ])->save();

            if (! $owner->is_platform_super_admin && $owner->current_business_id === null) {
                $owner->forceFill([
                    'current_business_id' => $business->id,
                ])->save();
            }

            $this->markMaterializing($account);

            return $business;
        });
    }

    public function findBusiness(SyncAccount $account): ?Business
    {
        return Business::query()
            ->where('public_uuid', $account->business_public_uuid)
            ->first();
    }

    public function materialize(SyncAccount $account): void
    {
        $business = $this->ensureBusiness($account);

        if ($business === null || ! $this->shouldMaterialize($account)) {
            return;
        }

        $cursor = (int) $account->materialized_operation_id;
        $limit = 8000;

        $operations = SyncOperation::query()
            ->where('sync_account_id', $account->id)
            ->where('id', '>', $cursor)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->all();

        if ($operations === []) {
            return;
        }

        usort($operations, function (SyncOperation $left, SyncOperation $right): int {
            $leftDelete = $left->op === 'delete' ? 1 : 0;
            $rightDelete = $right->op === 'delete' ? 1 : 0;

            if ($leftDelete !== $rightDelete) {
                return $leftDelete <=> $rightDelete;
            }

            $rank = $this->catalogRank((string) $left->entity_type) <=> $this->catalogRank((string) $right->entity_type);

            if ($rank !== 0) {
                return $rank;
            }

            return (int) $left->id <=> (int) $right->id;
        });

        $pending = $operations;

        for ($round = 0; $round < 8 && $pending !== []; $round++) {
            $next = [];
            $progress = false;

            foreach ($pending as $operation) {
                $result = $this->applier->applyCanonical($business, [
                    'uuid' => (string) $operation->uuid,
                    'entity_type' => (string) $operation->entity_type,
                    'entity_uuid' => (string) $operation->entity_uuid,
                    'op' => (string) $operation->op,
                    'payload' => is_array($operation->payload) ? $operation->payload : [],
                ]);

                if ($result === 'deferred') {
                    $next[] = $operation;

                    continue;
                }

                $progress = true;
            }

            if (! $progress) {
                break;
            }

            $pending = $next;
        }

        $hitLimit = count($operations) >= $limit;
        $nextCursor = (int) collect($operations)->max(fn (SyncOperation $operation): int => (int) $operation->id);

        if ($pending !== [] && $hitLimit) {
            $blocked = (int) collect($pending)->min(fn (SyncOperation $operation): int => (int) $operation->id);
            $nextCursor = max($cursor, $blocked - 1);
        }

        if ($nextCursor > $cursor) {
            $account->forceFill(['materialized_operation_id' => $nextCursor])->save();
        }
    }

    public function shouldMaterialize(SyncAccount $account): bool
    {
        if (! Schema::hasColumn('sync_accounts', 'materializes')) {
            return false;
        }

        return (bool) $account->materializes;
    }

    private function markMaterializing(SyncAccount $account): void
    {
        if (! Schema::hasColumn('sync_accounts', 'materializes')) {
            return;
        }

        $account->forceFill(['materializes' => true])->save();
    }

    private function catalogRank(string $table): int
    {
        $class = SyncCatalog::classForTable($table);

        if ($class === null) {
            return 999;
        }

        $index = array_search($class, SyncCatalog::models(), true);

        return $index === false ? 999 : (int) $index;
    }

    /**
     * @return array{plan: string, subscription_status: string, subscription_ends_at: string|null}|null
     */
    public function officeStatus(SyncAccount $account): ?array
    {
        $business = $this->findBusiness($account);

        if ($business === null) {
            return null;
        }

        return [
            'plan' => $business->plan->value,
            'subscription_status' => $business->subscription_status->value,
            'subscription_ends_at' => $business->subscription_ends_at?->toIso8601String(),
        ];
    }
}
