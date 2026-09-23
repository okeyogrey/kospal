<?php

namespace App\Services;

use App\Contracts\FeatureFlagService;
use App\Enums\Plan;
use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Enforces plan branch caps when a business moves to a smaller edition.
 *
 * Extra locations are paused (not deleted). Plan-paused locations cannot be
 * swapped back in later; they reopen only after an upgrade that raises the cap.
 */
class PlanBranchCapService
{
    public function __construct(
        protected FeatureFlagService $limits,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  list<int|string>  $keepBranchIds
     */
    public function enforceOnPlanChange(
        Business $business,
        Plan $plan,
        array $keepBranchIds,
        User $actor,
    ): void {
        $max = $plan->maxBranches();
        $active = $this->activeBranches($business);

        if ($active->count() <= $max) {
            return;
        }

        $keepIds = $this->validatedKeepIds($plan, $keepBranchIds, $active);

        $paused = $active->reject(fn (Branch $branch) => in_array($branch->id, $keepIds, true));

        foreach ($paused as $branch) {
            $branch->forceFill([
                'is_active' => false,
                'plan_paused_max_branches' => $max,
            ])->save();
        }

        $this->audit->log(
            action: 'branch.plan_paused',
            auditable: $business,
            metadata: [
                'plan' => $plan->value,
                'max_branches' => $max,
                'kept_branch_ids' => $keepIds,
                'paused_branch_ids' => $paused->pluck('id')->values()->all(),
            ],
            actor: $actor,
            businessId: $business->id,
        );
    }

    public function canReopen(Branch $branch): bool
    {
        if ($branch->is_active) {
            return false;
        }

        if (! $this->limits->canAddBranch($branch->business)) {
            return false;
        }

        return $this->planPauseAllowsReopen($branch);
    }

    public function assertCanReopen(Branch $branch): void
    {
        if (! $this->planPauseAllowsReopen($branch)) {
            throw ValidationException::withMessages([
                'is_active' => 'This location was paused for your current plan. Upgrade to reopen it. You cannot swap it for a location that stayed open.',
            ]);
        }

        $this->limits->assertCanAddBranch($branch->business);
    }

    /**
     * @return Collection<int, Branch>
     */
    public function activeBranches(Business $business): Collection
    {
        return Branch::query()
            ->forBusiness($business)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  list<int|string>  $keepBranchIds
     * @param  Collection<int, Branch>  $active
     * @return list<int>
     */
    protected function validatedKeepIds(
        Plan $plan,
        array $keepBranchIds,
        Collection $active,
    ): array {
        $max = $plan->maxBranches();
        $planName = $plan->config()['name'];
        $keepIds = array_values(array_unique(array_map('intval', $keepBranchIds)));
        $activeIds = $active->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($keepIds === []) {
            throw ValidationException::withMessages([
                'keep_branch_ids' => sprintf(
                    'This %s license allows %d open location%s. Choose which %s stay%s open. Extra shops will be paused, not deleted.',
                    $planName,
                    $max,
                    $max === 1 ? '' : 's',
                    $max === 1 ? 'shop' : 'shops',
                    $max === 1 ? 's' : '',
                ),
            ]);
        }

        if (count($keepIds) > $max) {
            throw ValidationException::withMessages([
                'keep_branch_ids' => sprintf(
                    'This %s license allows %d open location%s. Unselect extra shops.',
                    $planName,
                    $max,
                    $max === 1 ? '' : 's',
                ),
            ]);
        }

        foreach ($keepIds as $id) {
            if (! in_array($id, $activeIds, true)) {
                throw ValidationException::withMessages([
                    'keep_branch_ids' => 'Choose from currently open locations only.',
                ]);
            }
        }

        return $keepIds;
    }

    protected function planPauseAllowsReopen(Branch $branch): bool
    {
        if ($branch->plan_paused_max_branches === null) {
            return true;
        }

        return $branch->business->plan->maxBranches() > $branch->plan_paused_max_branches;
    }
}
