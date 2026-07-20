<?php

namespace App\Services;

use App\Enums\LicenseActivationMode;
use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Deployment;
use App\Support\Licensing\LicenseKeyCodec;
use App\Support\Licensing\MachineId;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Desktop licensing mutations and entitlement clock.
 *
 * Retail / business-domain services must not call this for catalog, sales,
 * or inventory logic. Write gates go through LicensingService; feature gates
 * go through FeatureFlagService (plan edition set here).
 */
class LicenseService
{
    public function __construct(
        protected AuditLogger $audit,
        protected MachineId $machineId,
        protected LicenseKeyCodec $keys,
    ) {}

    public function machineId(): string
    {
        return $this->machineId->get();
    }

    /**
     * Start a time-boxed trial for a newly onboarded business.
     */
    public function startTrial(Business $business, ?User $actor = null, ?Plan $edition = null): Business
    {
        $edition ??= $this->defaultEdition();
        $days = max(1, (int) config('deployment.license.trial_days', 30));
        $endsAt = now()->addDays($days)->endOfDay();

        $business->forceFill([
            'plan' => $edition,
            'subscription_status' => SubscriptionStatus::Trial,
            'subscription_ends_at' => $endsAt,
            'license_activation_mode' => LicenseActivationMode::Trial,
            'license_key' => null,
            'license_id' => null,
            'licensed_machine_id' => $this->machineId(),
            'licensed_at' => now(),
        ])->save();

        $this->audit->log(
            action: 'license.trial_started',
            auditable: $business,
            metadata: [
                'plan' => $edition->value,
                'trial_days' => $days,
                'expires_at' => $endsAt->toIso8601String(),
                'machine_id' => $business->licensed_machine_id,
            ],
            actor: $actor,
            businessId: $business->id,
        );

        return $business->fresh();
    }

    /**
     * Activate with a purchased online license key (local verify or license server).
     *
     * @param  array{license_key: string}  $data
     */
    public function activateOnline(Business $business, User $actor, array $data): Business
    {
        $this->assertDesktop();

        $rawKey = trim($data['license_key']);
        $claims = $this->resolveOnlineClaims($rawKey);

        return $this->applyActivation(
            business: $business,
            actor: $actor,
            claims: $claims,
            mode: LicenseActivationMode::Online,
            rawKey: $rawKey,
        );
    }

    /**
     * Activate with a machine-bound offline activation code.
     *
     * @param  array{activation_code: string}  $data
     */
    public function activateOffline(Business $business, User $actor, array $data): Business
    {
        $this->assertDesktop();

        $rawCode = trim($data['activation_code']);

        try {
            $claims = $this->keys->decode($rawCode);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'activation_code' => $e->getMessage(),
            ]);
        }

        if ($claims['machine_id'] === null) {
            throw ValidationException::withMessages([
                'activation_code' => 'This activation code is not machine-bound. Use online activation with a license key instead.',
            ]);
        }

        if (! hash_equals($this->machineId(), $claims['machine_id'])) {
            throw ValidationException::withMessages([
                'activation_code' => 'This activation code was issued for a different machine.',
            ]);
        }

        return $this->applyActivation(
            business: $business,
            actor: $actor,
            claims: $claims,
            mode: LicenseActivationMode::Offline,
            rawKey: $rawCode,
        );
    }

    public function expireIfPastDue(Business $business): Business
    {
        if (! Deployment::isDesktop()) {
            return $business;
        }

        $status = $business->subscription_status;

        if (
            ! in_array($status, [SubscriptionStatus::Trial, SubscriptionStatus::Active], true)
            || $business->subscription_ends_at === null
            || ! $business->subscription_ends_at->isPast()
        ) {
            return $business;
        }

        $endsAt = $business->subscription_ends_at->toIso8601String();

        $business->forceFill([
            'subscription_status' => SubscriptionStatus::Expired,
        ])->save();

        $this->audit->log(
            action: 'license.expired',
            auditable: $business,
            metadata: [
                'previous_status' => $status->value,
                'expires_at' => $endsAt,
                'activation_mode' => $business->license_activation_mode?->value,
            ],
            businessId: $business->id,
        );

        return $business->fresh();
    }

    public function allowsWriteAccess(Business $business): bool
    {
        if (! $business->is_active) {
            return false;
        }

        $this->expireIfPastDue($business);

        return $business->fresh()->subscription_status->allowsWriteAccess();
    }

    /**
     * Payload for Settings > License and shared UI.
     *
     * @return array{
     *     machine_id: string,
     *     status: string,
     *     activation_mode: string|null,
     *     plan: string,
     *     expires_at: string|null,
     *     days_remaining: int|null,
     *     licensed_at: string|null,
     *     licensed_machine_id: string|null,
     *     license_id: string|null,
     *     allows_write_access: bool,
     *     is_trial: bool,
     *     is_expired: bool,
     *     features: list<string>,
     * }
     */
    public function statusPayload(Business $business): array
    {
        $this->expireIfPastDue($business);
        $business->refresh();

        $endsAt = $business->subscription_ends_at;
        $daysRemaining = null;

        if ($endsAt !== null && $endsAt->isFuture()) {
            $daysRemaining = (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay());
        } elseif ($endsAt !== null && $endsAt->isToday()) {
            $daysRemaining = 0;
        }

        /** @var list<string> $features */
        $features = $business->plan->config()['features'];

        return [
            'machine_id' => $this->machineId(),
            'status' => $business->subscription_status->value,
            'activation_mode' => $business->license_activation_mode?->value,
            'plan' => $business->plan->value,
            'expires_at' => $endsAt?->toDateString(),
            'days_remaining' => $daysRemaining,
            'licensed_at' => $business->licensed_at?->toIso8601String(),
            'licensed_machine_id' => $business->licensed_machine_id,
            'license_id' => $business->license_id,
            'allows_write_access' => $business->is_active && $business->subscription_status->allowsWriteAccess(),
            'is_trial' => $business->subscription_status === SubscriptionStatus::Trial,
            'is_expired' => $business->subscription_status === SubscriptionStatus::Expired,
            'features' => $features,
        ];
    }

    /**
     * Issue a signed license key or offline activation code (ops / artisan).
     *
     * @param  array{
     *     edition: string,
     *     days?: int|null,
     *     expires_at?: string|null,
     *     machine_id?: string|null,
     * }  $data
     */
    public function issueKey(array $data): string
    {
        $expiresAt = $data['expires_at'] ?? null;

        if ($expiresAt === null && isset($data['days'])) {
            $expiresAt = now()->addDays(max(1, (int) $data['days']))->toDateString();
        }

        return $this->keys->issue([
            'edition' => $data['edition'],
            'expires_at' => $expiresAt,
            'machine_id' => $data['machine_id'] ?? null,
        ]);
    }

    /**
     * @param  array{
     *     license_id: string,
     *     edition: Plan,
     *     expires_at: CarbonInterface|null,
     *     machine_id: string|null,
     *     issued_at: string|null,
     * }  $claims
     */
    protected function applyActivation(
        Business $business,
        User $actor,
        array $claims,
        LicenseActivationMode $mode,
        string $rawKey,
    ): Business {
        if ($claims['expires_at'] !== null && $claims['expires_at']->isPast()) {
            throw ValidationException::withMessages([
                $mode === LicenseActivationMode::Offline ? 'activation_code' : 'license_key' => 'This license has already expired.',
            ]);
        }

        $machineId = $this->machineId();
        $previous = [
            'plan' => $business->plan->value,
            'subscription_status' => $business->subscription_status->value,
            'subscription_ends_at' => $business->subscription_ends_at?->toDateString(),
            'activation_mode' => $business->license_activation_mode?->value,
        ];

        $business->forceFill([
            'plan' => $claims['edition'],
            'subscription_status' => SubscriptionStatus::Active,
            'subscription_ends_at' => $claims['expires_at'],
            'license_activation_mode' => $mode,
            'license_key' => $rawKey,
            'license_id' => $claims['license_id'],
            'licensed_machine_id' => $machineId,
            'licensed_at' => now(),
        ])->save();

        $this->audit->log(
            action: 'license.activated',
            auditable: $business,
            metadata: [
                'previous' => $previous,
                'mode' => $mode->value,
                'plan' => $claims['edition']->value,
                'license_id' => $claims['license_id'],
                'expires_at' => $claims['expires_at']?->toDateString(),
                'machine_id' => $machineId,
            ],
            actor: $actor,
            businessId: $business->id,
        );

        return $business->fresh();
    }

    /**
     * @return array{
     *     license_id: string,
     *     edition: Plan,
     *     expires_at: CarbonInterface|null,
     *     machine_id: string|null,
     *     issued_at: string|null,
     * }
     */
    protected function resolveOnlineClaims(string $rawKey): array
    {
        $serverUrl = trim((string) config('deployment.license.server_url'));

        if ($serverUrl !== '') {
            return $this->activateViaServer($serverUrl, $rawKey);
        }

        try {
            $claims = $this->keys->decode($rawKey);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'license_key' => $e->getMessage(),
            ]);
        }

        if ($claims['machine_id'] !== null) {
            throw ValidationException::withMessages([
                'license_key' => 'This key is machine-bound. Use offline activation instead.',
            ]);
        }

        return $claims;
    }

    /**
     * @return array{
     *     license_id: string,
     *     edition: Plan,
     *     expires_at: CarbonInterface|null,
     *     machine_id: string|null,
     *     issued_at: string|null,
     * }
     */
    protected function activateViaServer(string $serverUrl, string $rawKey): array
    {
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->post(rtrim($serverUrl, '/').'/activate', [
                    'license_key' => $rawKey,
                    'machine_id' => $this->machineId(),
                    'app_version' => config('deployment.version'),
                ]);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'license_key' => 'Could not reach the license server. Try again or use offline activation.',
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'license_key' => $response->json('message') ?? 'Online license activation failed.',
            ]);
        }

        $edition = Plan::tryFrom((string) $response->json('edition', ''));
        $licenseId = (string) $response->json('license_id', '');

        if ($edition === null || $licenseId === '') {
            throw ValidationException::withMessages([
                'license_key' => 'License server returned an incomplete response.',
            ]);
        }

        $expiresRaw = $response->json('expires_at');
        $expiresAt = is_string($expiresRaw) && $expiresRaw !== ''
            ? Carbon::parse($expiresRaw)->startOfDay()
            : null;

        return [
            'license_id' => $licenseId,
            'edition' => $edition,
            'expires_at' => $expiresAt,
            'machine_id' => null,
            'issued_at' => now()->toIso8601String(),
        ];
    }

    protected function defaultEdition(): Plan
    {
        $edition = (string) config('deployment.license.default_edition', Plan::Enterprise->value);

        return Plan::tryFrom($edition) ?? Plan::Enterprise;
    }

    protected function assertDesktop(): void
    {
        if (! Deployment::isDesktop()) {
            throw ValidationException::withMessages([
                'license_key' => 'License activation is only available in desktop mode.',
            ]);
        }
    }
}
