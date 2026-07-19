<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(
        protected TenantContext $tenant,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        array $metadata = [],
        ?User $actor = null,
        ?int $businessId = null,
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();
        $actor ??= $this->tenant->user() ?? $request?->user();

        return AuditLog::query()->create([
            'business_id' => $businessId ?? $this->tenant->businessId() ?? ($auditable?->getAttribute('business_id')),
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
