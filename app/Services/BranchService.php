<?php

namespace App\Services;

use App\Contracts\FeatureFlagService;
use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class BranchService
{
    public function __construct(
        protected FeatureFlagService $limits,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     code?: string|null,
     *     address?: string|null,
     *     city?: string|null,
     *     phone?: string|null,
     *     is_active?: bool,
     * }  $data
     */
    public function create(Business $business, array $data, User $actor): Branch
    {
        $isActive = $data['is_active'] ?? true;

        if ($isActive) {
            $this->limits->assertCanAddBranch($business);
        }

        $branch = Branch::query()->create([
            'business_id' => $business->id,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $isActive,
        ]);

        $this->audit->log(
            action: 'branch.created',
            auditable: $branch,
            actor: $actor,
            businessId: $business->id,
        );

        return $branch;
    }

    /**
     * @param  array{
     *     name?: string,
     *     code?: string|null,
     *     address?: string|null,
     *     city?: string|null,
     *     phone?: string|null,
     *     is_active?: bool,
     * }  $data
     */
    public function update(Branch $branch, array $data, User $actor): Branch
    {
        $wasActive = $branch->is_active;
        $willBeActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $wasActive;

        if (! $wasActive && $willBeActive) {
            $this->limits->assertCanAddBranch($branch->business);
        }

        if ($wasActive && ! $willBeActive) {
            $activeCount = $this->limits->activeBranchCount($branch->business);
            if ($activeCount <= 1) {
                throw ValidationException::withMessages([
                    'is_active' => 'A business must keep at least one active branch.',
                ]);
            }
        }

        $branch->update($data);

        $this->audit->log(
            action: 'branch.updated',
            auditable: $branch,
            metadata: $data,
            actor: $actor,
            businessId: $branch->business_id,
        );

        return $branch->refresh();
    }

    public function delete(Branch $branch, User $actor): void
    {
        if ($branch->is_active && $this->limits->activeBranchCount($branch->business) <= 1) {
            throw ValidationException::withMessages([
                'branch' => 'A business must keep at least one active branch.',
            ]);
        }

        $businessId = $branch->business_id;
        $branch->delete();

        $this->audit->log(
            action: 'branch.deleted',
            metadata: ['branch_id' => $branch->id, 'name' => $branch->name],
            actor: $actor,
            businessId: $businessId,
        );
    }
}
