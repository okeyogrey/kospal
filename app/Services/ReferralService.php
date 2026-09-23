<?php

namespace App\Services;

use App\Enums\Plan;
use App\Enums\ReferralCreditSide;
use App\Enums\ReferralCreditStatus;
use App\Enums\ReferralStatus;
use App\Models\Business;
use App\Models\Referral;
use App\Models\ReferralAccount;
use App\Models\ReferralCode;
use App\Models\ReferralCredit;
use App\Models\User;
use App\Notifications\ReferralInviteNotification;
use App\Services\Referrals\ReferralClient;
use App\Support\Audit\AuditLogger;
use App\Support\Deployment;
use App\Support\Licensing\MachineId;
use App\Support\Plans\PlanPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReferralService
{
    public function __construct(
        protected ReferralClient $remote,
        protected AuditLogger $audit,
        protected MachineId $machineId,
    ) {}

    public function usesRemote(): bool
    {
        return Deployment::isDesktop() && $this->remote->enabled();
    }

    public function captureCode(?string $code): void
    {
        $normalized = $this->normalizeCode($code);

        if ($normalized === null) {
            return;
        }

        session(['referral_code' => $normalized]);
    }

    public function capturedCode(): ?string
    {
        $code = session('referral_code');

        return is_string($code) ? $this->normalizeCode($code) : null;
    }

    public function forgetCapturedCode(): void
    {
        session()->forget('referral_code');
    }

    /**
     * @return array{code: string, referrer_name: string, expires_at: string, usable: bool}|null
     */
    public function previewOrNull(?string $code): ?array
    {
        $normalized = $this->normalizeCode($code);

        if ($normalized === null) {
            return null;
        }

        try {
            return $this->preview($normalized);
        } catch (ValidationException) {
            return null;
        }
    }

    /**
     * @return array{code: string, referrer_name: string, expires_at: string, usable: bool}
     */
    public function preview(string $code): array
    {
        $normalized = $this->normalizeCode($code)
            ?? throw ValidationException::withMessages([
                'referral_code' => 'Enter a valid invite code.',
            ]);

        if ($this->usesRemote()) {
            $payload = $this->remote->preview($normalized);

            return [
                'code' => (string) ($payload['code'] ?? $normalized),
                'referrer_name' => (string) ($payload['referrer_name'] ?? 'a KOSPAL shop'),
                'referrer_email' => $payload['referrer_email'] ?? null,
                'expires_at' => (string) ($payload['expires_at'] ?? ''),
                'usable' => (bool) ($payload['usable'] ?? false),
            ];
        }

        $record = ReferralCode::query()
            ->with('account')
            ->where('code', $normalized)
            ->first();

        if ($record === null) {
            throw ValidationException::withMessages([
                'referral_code' => 'This invite code was not found.',
            ]);
        }

        return [
            'code' => $record->code,
            'referrer_name' => $record->account?->business_name ?? 'a KOSPAL shop',
            'referrer_email' => $record->account?->owner_email,
            'expires_at' => $record->expires_at->toDayDateTimeString(),
            'usable' => $record->isUsable(),
        ];
    }

    public function assertRedeemable(?string $code, User $owner, ?string $machineId = null): void
    {
        $normalized = $this->normalizeCode($code);

        if ($normalized === null) {
            return;
        }

        $preview = $this->preview($normalized);

        if (! $preview['usable']) {
            throw ValidationException::withMessages([
                'referral_code' => 'This invite code has expired. Ask for a new one.',
            ]);
        }

        $referrerEmail = ReferralAccount::normalizeEmail($preview['referrer_email'] ?? null);

        if ($referrerEmail !== '' && $referrerEmail === ReferralAccount::normalizeEmail($owner->email)) {
            throw ValidationException::withMessages([
                'referral_code' => 'You cannot invite yourself.',
            ]);
        }
    }

    public function ensureAccount(Business $business, ?User $owner = null, ?string $machineId = null): ReferralAccount
    {
        $owner ??= $business->owner;
        $machineId ??= $this->localMachineId();

        $account = ReferralAccount::query()->firstOrNew([
            'public_uuid' => $business->public_uuid,
        ]);

        $account->fill([
            'business_id' => $business->id,
            'owner_user_id' => $owner?->id,
            'owner_email' => ReferralAccount::normalizeEmail($owner?->email),
            'business_name' => $business->name,
            'machine_id' => $machineId ?: $account->machine_id,
            'is_active' => $business->is_active,
            'last_seen_at' => now(),
            'first_paid_period_at' => $business->first_paid_period_at ?? $account->first_paid_period_at,
        ]);
        $account->save();

        if ($this->usesRemote()) {
            $payload = $this->remote->registerAccount([
                'public_uuid' => $account->public_uuid,
                'owner_email' => $account->owner_email,
                'business_name' => $account->business_name,
                'machine_id' => $account->machine_id,
            ]);

            if (isset($payload['token']) && is_string($payload['token'])) {
                $account->remote_token = $payload['token'];
                $account->save();
            }
        }

        return $account->fresh();
    }

    public function issueCode(Business $business, User $actor): ReferralCode
    {
        $account = $this->ensureAccount($business, $actor);

        if ($this->usesRemote()) {
            $payload = $this->remote->issueCode((string) $account->remote_token);
            $code = ReferralCode::query()->updateOrCreate(
                ['code' => (string) $payload['code']],
                [
                    'referral_account_id' => $account->id,
                    'created_by_user_id' => $actor->id,
                    'expires_at' => $payload['expires_at'] ?? now()->addDays($this->codeExpiresDays()),
                    'revoked_at' => null,
                ],
            );
        } else {
            $code = ReferralCode::query()->create([
                'referral_account_id' => $account->id,
                'created_by_user_id' => $actor->id,
                'code' => $this->uniqueCode(),
                'expires_at' => now()->addDays($this->codeExpiresDays()),
            ]);
        }

        $this->audit->log(
            action: 'referral.code_issued',
            auditable: $code,
            metadata: [
                'code' => $code->code,
                'expires_at' => $code->expires_at?->toIso8601String(),
            ],
            actor: $actor,
            businessId: $business->id,
        );

        return $code;
    }

    public function redeemForOnboarding(Business $referred, User $owner, ?string $code, ?string $machineId = null): ?Referral
    {
        $normalized = $this->normalizeCode($code);

        if ($normalized === null) {
            return null;
        }

        $machineId ??= $this->localMachineId();
        $account = $this->ensureAccount($referred, $owner, $machineId);

        if ($this->usesRemote()) {
            $payload = $this->remote->redeem([
                'code' => $normalized,
                'public_uuid' => $account->public_uuid,
                'owner_email' => $account->owner_email,
                'business_name' => $account->business_name,
                'machine_id' => $account->machine_id,
            ]);

            if (isset($payload['token']) && is_string($payload['token'])) {
                $account->remote_token = $payload['token'];
                $account->save();
            }

            $this->forgetCapturedCode();

            return null;
        }

        $referral = $this->redeemLocal($account, $normalized, $owner);
        $this->forgetCapturedCode();

        $this->audit->log(
            action: 'referral.redeemed',
            auditable: $referral,
            metadata: [
                'code' => $normalized,
                'referrer_account_id' => $referral->referrer_account_id,
            ],
            actor: $owner,
            businessId: $referred->id,
        );

        return $referral;
    }

    public function redeemLocal(ReferralAccount $referred, string $code, ?User $owner = null): Referral
    {
        $normalized = $this->normalizeCode($code)
            ?? throw ValidationException::withMessages([
                'referral_code' => 'Enter a valid invite code.',
            ]);

        $record = ReferralCode::query()
            ->with('account')
            ->where('code', $normalized)
            ->first();

        if ($record === null || $record->account === null) {
            throw ValidationException::withMessages([
                'referral_code' => 'This invite code was not found.',
            ]);
        }

        if (! $record->isUsable()) {
            throw ValidationException::withMessages([
                'referral_code' => 'This invite code has expired. Ask for a new one.',
            ]);
        }

        $this->assertNotSelfReferral($record->account, $referred, $owner);

        if (Referral::query()->where('referred_account_id', $referred->id)->exists()) {
            throw ValidationException::withMessages([
                'referral_code' => 'This shop already used an invite.',
            ]);
        }

        $days = $this->qualifyAfterDays();

        return Referral::query()->create([
            'referral_code_id' => $record->id,
            'referrer_account_id' => $record->referral_account_id,
            'referred_account_id' => $referred->id,
            'status' => ReferralStatus::Pending,
            'onboarded_at' => now(),
            'qualifies_at' => now()->addDays($days),
        ]);
    }

    public function qualifyDue(): int
    {
        if ($this->usesRemote()) {
            return 0;
        }

        $qualified = 0;

        Referral::query()
            ->with(['referredAccount.business', 'referrerAccount'])
            ->where('status', ReferralStatus::Pending)
            ->where('qualifies_at', '<=', now())
            ->orderBy('id')
            ->each(function (Referral $referral) use (&$qualified): void {
                if ($this->qualifyOne($referral)) {
                    $qualified++;
                }
            });

        return $qualified;
    }

    public function applyToPayment(Business $business): array
    {
        $account = $this->ensureAccount($business);

        if ($this->usesRemote()) {
            $applied = $this->remote->applyPayment((string) $account->remote_token);
            $percent = (int) ($applied['applied_percent'] ?? 0);

            if ($business->first_paid_period_at === null) {
                $business->forceFill(['first_paid_period_at' => now()])->save();
            }

            $account->forceFill([
                'first_paid_period_at' => $account->first_paid_period_at ?? now(),
                'cached_available_percent' => (int) ($applied['remaining_percent'] ?? 0),
            ])->save();

            return PlanPricing::quote($business->plan, $business->currency, $percent);
        }

        return DB::transaction(function () use ($business, $account) {
            $locked = ReferralAccount::query()->lockForUpdate()->findOrFail($account->id);
            $percent = $this->consumeCredits($locked);
            $now = now();

            if ($business->first_paid_period_at === null) {
                $business->forceFill(['first_paid_period_at' => $now])->save();
            }

            if ($locked->first_paid_period_at === null) {
                $locked->forceFill(['first_paid_period_at' => $now])->save();
            }

            $locked->forceFill([
                'cached_available_percent' => $this->availablePercentFor($locked->fresh()),
            ])->save();

            if ($percent > 0) {
                $this->audit->log(
                    action: 'referral.credit_applied',
                    auditable: $business,
                    metadata: [
                        'applied_percent' => $percent,
                    ],
                    businessId: $business->id,
                );
            }

            return PlanPricing::quote($business->plan, $business->currency, $percent);
        });
    }

    public function availablePercent(Business $business): int
    {
        $account = ReferralAccount::query()
            ->where('public_uuid', $business->public_uuid)
            ->first();

        if ($account === null) {
            return 0;
        }

        if ($this->usesRemote()) {
            try {
                $dashboard = $this->remote->dashboard((string) $account->remote_token);
                $percent = (int) ($dashboard['available_percent'] ?? 0);
                $account->forceFill(['cached_available_percent' => $percent])->save();

                return $percent;
            } catch (ValidationException) {
                return (int) $account->cached_available_percent;
            }
        }

        return $this->availablePercentFor($account);
    }

    /**
     * @return array<string, mixed>
     */
    public function quote(Business $business, ?Plan $plan = null): array
    {
        $plan ??= $business->plan;
        $available = $this->availablePercent($business);
        $quote = PlanPricing::quote($plan, $business->currency, min(100, $available));

        return [
            ...$quote,
            'plan' => $plan->value,
            'available_percent' => $available,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardPayload(Business $business, User $actor): array
    {
        $account = $this->ensureAccount($business, $actor);

        if ($this->usesRemote()) {
            $remote = $this->remote->dashboard((string) $account->remote_token);
            $account->forceFill([
                'cached_available_percent' => (int) ($remote['available_percent'] ?? 0),
            ])->save();

            return [
                ...$remote,
                'connected_to_server' => true,
                'server_required' => false,
            ];
        }

        $active = $account->codes()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        $referrals = $account->referrerReferrals()
            ->with('referredAccount')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Referral $referral) => [
                'id' => $referral->id,
                'status' => $referral->status->value,
                'business_name' => $referral->referredAccount?->business_name,
                'onboarded_at' => $referral->onboarded_at?->toDateString(),
                'qualifies_at' => $referral->qualifies_at?->toDateString(),
                'qualified_at' => $referral->qualified_at?->toDateString(),
            ])
            ->all();

        $incoming = $account->referredReferrals()->with('referrerAccount')->first();
        $available = $this->availablePercentFor($account);

        return [
            'connected_to_server' => false,
            'server_required' => Deployment::isDesktop() && ! $this->remote->enabled(),
            'available_percent' => $available,
            'applied_percent_cap' => min(100, $available),
            'credit_percent' => $this->creditPercent(),
            'referred_by' => $incoming === null ? null : [
                'business_name' => $incoming->referrerAccount?->business_name,
                'status' => $incoming->status->value,
                'qualifies_at' => $incoming->qualifies_at?->toDateString(),
            ],
            'active_code' => $active === null ? null : $this->codePayload($active),
            'share' => $active === null ? null : $this->sharePayload($active, $actor),
            'referrals' => $referrals,
            'stats' => [
                'pending' => $account->referrerReferrals()->where('status', ReferralStatus::Pending)->count(),
                'qualified' => $account->referrerReferrals()->where('status', ReferralStatus::Qualified)->count(),
                'voided' => $account->referrerReferrals()->where('status', ReferralStatus::Voided)->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function platformIndexPayload(?string $status = null): array
    {
        $allowed = [...ReferralStatus::values(), 'all'];
        if ($status === null || $status === '' || ! in_array($status, $allowed, true)) {
            $status = 'all';
        }

        $referrals = Referral::query()
            ->with(['referrerAccount.business', 'referredAccount.business', 'voidedBy', 'credits'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Referral $referral) => [
                'id' => $referral->id,
                'status' => $referral->status->value,
                'onboarded_at' => $referral->onboarded_at?->toIso8601String(),
                'qualifies_at' => $referral->qualifies_at?->toIso8601String(),
                'qualified_at' => $referral->qualified_at?->toIso8601String(),
                'void_reason' => $referral->void_reason,
                'voided_by' => $referral->voidedBy?->name,
                'referrer' => [
                    'business_id' => $referral->referrerAccount?->business_id,
                    'name' => $referral->referrerAccount?->business_name,
                    'email' => $referral->referrerAccount?->owner_email,
                ],
                'referred' => [
                    'business_id' => $referral->referredAccount?->business_id,
                    'name' => $referral->referredAccount?->business_name,
                    'email' => $referral->referredAccount?->owner_email,
                    'is_active' => $referral->referredAccount?->is_active,
                ],
                'credits' => $referral->credits->map(fn (ReferralCredit $credit) => [
                    'side' => $credit->side->value,
                    'percent' => $credit->percent,
                    'remaining_percent' => $credit->remaining_percent,
                    'status' => $credit->status->value,
                ])->all(),
            ]);

        return [
            'referrals' => $referrals,
            'filters' => ['status' => $status],
            'statuses' => ReferralStatus::values(),
        ];
    }

    public function voidReferral(Referral $referral, User $admin, ?string $reason = null): Referral
    {
        if ($referral->status === ReferralStatus::Voided) {
            throw ValidationException::withMessages([
                'referral' => 'This referral is already voided.',
            ]);
        }

        return DB::transaction(function () use ($referral, $admin, $reason) {
            $referral->update([
                'status' => ReferralStatus::Voided,
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by_user_id' => $admin->id,
            ]);

            $referral->credits()
                ->where('remaining_percent', '>', 0)
                ->where('status', '!=', ReferralCreditStatus::Voided)
                ->update([
                    'status' => ReferralCreditStatus::Voided,
                    'remaining_percent' => 0,
                ]);

            $this->audit->log(
                action: 'referral.voided',
                auditable: $referral,
                metadata: [
                    'reason' => $reason,
                ],
                actor: $admin,
                businessId: $referral->referrerAccount?->business_id,
            );

            return $referral->fresh();
        });
    }

    public function sendEmailInvite(Business $business, User $actor, string $email): void
    {
        $email = ReferralAccount::normalizeEmail($email);

        if ($email === ReferralAccount::normalizeEmail($actor->email)) {
            throw ValidationException::withMessages([
                'email' => 'You cannot invite yourself.',
            ]);
        }

        $account = $this->ensureAccount($business, $actor);

        if ($this->usesRemote()) {
            $this->remote->sendEmail((string) $account->remote_token, $email);

            return;
        }

        $this->sendEmailForAccount($account, $email, $actor);
    }

    public function sendEmailForAccount(ReferralAccount $account, string $email, ?User $actor = null): void
    {
        $email = ReferralAccount::normalizeEmail($email);

        if ($email === $account->owner_email) {
            throw ValidationException::withMessages([
                'email' => 'You cannot invite yourself.',
            ]);
        }

        $code = $account->codes()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($code === null) {
            $code = ReferralCode::query()->create([
                'referral_account_id' => $account->id,
                'created_by_user_id' => $actor?->id,
                'code' => $this->uniqueCode(),
                'expires_at' => now()->addDays($this->codeExpiresDays()),
            ]);
        }

        Notification::route('mail', $email)->notify(new ReferralInviteNotification(
            $this->sharePayload($code, $actor),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function registerRemoteAccount(array $data): array
    {
        $email = ReferralAccount::normalizeEmail($data['owner_email'] ?? null);

        $account = ReferralAccount::query()->firstOrNew([
            'public_uuid' => $data['public_uuid'],
        ]);

        if ($account->exists && $account->owner_email !== '' && $account->owner_email !== $email) {
            throw ValidationException::withMessages([
                'owner_email' => 'This shop is already registered to a different owner.',
            ]);
        }

        $plain = Str::random(48);

        $account->fill([
            'owner_email' => $email,
            'business_name' => $data['business_name'],
            'machine_id' => $data['machine_id'] ?? $account->machine_id,
            'is_active' => true,
            'last_seen_at' => now(),
            'api_token_hash' => hash('sha256', $plain),
        ]);
        $account->save();

        return [
            'public_uuid' => $account->public_uuid,
            'token' => $plain,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function issueCodeForAccount(ReferralAccount $account): array
    {
        $code = ReferralCode::query()->create([
            'referral_account_id' => $account->id,
            'code' => $this->uniqueCode(),
            'expires_at' => now()->addDays($this->codeExpiresDays()),
        ]);

        return $this->codePayload($code);
    }

    /**
     * @return array<string, mixed>
     */
    public function redeemRemote(array $data): array
    {
        $registered = $this->registerRemoteAccount($data);
        $account = ReferralAccount::query()
            ->where('public_uuid', $data['public_uuid'])
            ->firstOrFail();

        $this->redeemLocal($account, (string) $data['code']);

        return $registered;
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardForAccount(ReferralAccount $account): array
    {
        $active = $account->codes()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        $available = $this->availablePercentFor($account);

        return [
            'available_percent' => $available,
            'applied_percent_cap' => min(100, $available),
            'credit_percent' => $this->creditPercent(),
            'active_code' => $active === null ? null : $this->codePayload($active),
            'share' => $active === null ? null : $this->sharePayload($active),
            'referrals' => $account->referrerReferrals()
                ->with('referredAccount')
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (Referral $referral) => [
                    'id' => $referral->id,
                    'status' => $referral->status->value,
                    'business_name' => $referral->referredAccount?->business_name,
                    'onboarded_at' => $referral->onboarded_at?->toDateString(),
                    'qualifies_at' => $referral->qualifies_at?->toDateString(),
                    'qualified_at' => $referral->qualified_at?->toDateString(),
                ])
                ->all(),
            'stats' => [
                'pending' => $account->referrerReferrals()->where('status', ReferralStatus::Pending)->count(),
                'qualified' => $account->referrerReferrals()->where('status', ReferralStatus::Qualified)->count(),
                'voided' => $account->referrerReferrals()->where('status', ReferralStatus::Voided)->count(),
            ],
        ];
    }

    /**
     * @return array{applied_percent: int, remaining_percent: int}
     */
    public function applyPaymentForAccount(ReferralAccount $account): array
    {
        return DB::transaction(function () use ($account) {
            $locked = ReferralAccount::query()->lockForUpdate()->findOrFail($account->id);
            $percent = $this->consumeCredits($locked);

            if ($locked->first_paid_period_at === null) {
                $locked->forceFill(['first_paid_period_at' => now()])->save();
            }

            if ($locked->business && $locked->business->first_paid_period_at === null) {
                $locked->business->forceFill(['first_paid_period_at' => now()])->save();
            }

            $remaining = $this->availablePercentFor($locked->fresh());
            $locked->forceFill(['cached_available_percent' => $remaining])->save();

            return [
                'applied_percent' => $percent,
                'remaining_percent' => $remaining,
            ];
        });
    }

    public function heartbeat(ReferralAccount $account, bool $isActive): void
    {
        $account->forceFill([
            'is_active' => $isActive,
            'last_seen_at' => now(),
        ])->save();

        if ($account->business) {
            $account->business->forceFill(['is_active' => $isActive])->save();
        }
    }

    public function syncDesktopAccounts(): int
    {
        if (! $this->usesRemote()) {
            return 0;
        }

        $synced = 0;

        ReferralAccount::query()
            ->whereNotNull('remote_token')
            ->each(function (ReferralAccount $account) use (&$synced): void {
                $isActive = $account->business?->is_active ?? $account->is_active;
                $this->remote->heartbeat((string) $account->remote_token, (bool) $isActive);
                $synced++;
            });

        return $synced;
    }

    /**
     * @return array{code: string, expires_at: string, url: string}
     */
    public function codePayload(ReferralCode $code): array
    {
        return [
            'code' => $code->code,
            'expires_at' => $code->expires_at->toIso8601String(),
            'url' => $this->shareUrl($code),
        ];
    }

    /**
     * @return array{code: string, url: string, expires_at: string, inviter_name: string, whatsapp_url: string, message: string}
     */
    public function sharePayload(ReferralCode $code, ?User $actor = null): array
    {
        $url = $this->shareUrl($code);
        $name = $actor?->name ?? $code->account?->business_name ?? 'A KOSPAL shop';
        $message = "{$name} invited you to KOSPAL. New shops get 60 days free, and we both get 10% off the first payment after the trial. Join with {$url} or code {$code->code}. This invite expires in 3 days.";

        return [
            'code' => $code->code,
            'url' => $url,
            'expires_at' => $code->expires_at->toDayDateTimeString(),
            'inviter_name' => $name,
            'whatsapp_url' => 'https://wa.me/?text='.rawurlencode($message),
            'message' => $message,
        ];
    }

    public function shareUrl(ReferralCode $code): string
    {
        $base = trim((string) config('kospal.referral.public_url'));

        if ($base === '') {
            $base = trim((string) config('kospal.referral.server_url'));
        }

        if ($base === '') {
            $base = (string) config('app.url');
        }

        return rtrim($base, '/').'/r/'.$code->code;
    }

    public function creditPercent(): int
    {
        return max(1, (int) config('kospal.referral.credit_percent', 10));
    }

    protected function qualifyOne(Referral $referral): bool
    {
        $referred = $referral->referredAccount;

        if ($referred === null) {
            return false;
        }

        $businessActive = $referred->business?->is_active;
        $active = $businessActive ?? $referred->is_active;

        if (! $active) {
            $referral->update(['status' => ReferralStatus::Lapsed]);

            return false;
        }

        $percent = $this->creditPercent();

        DB::transaction(function () use ($referral, $percent): void {
            $referral->update([
                'status' => ReferralStatus::Qualified,
                'qualified_at' => now(),
            ]);

            ReferralCredit::query()->create([
                'referral_id' => $referral->id,
                'referral_account_id' => $referral->referrer_account_id,
                'side' => ReferralCreditSide::Referrer,
                'percent' => $percent,
                'remaining_percent' => $percent,
                'status' => ReferralCreditStatus::Available,
                'first_payment_only' => false,
                'available_at' => now(),
            ]);

            ReferralCredit::query()->create([
                'referral_id' => $referral->id,
                'referral_account_id' => $referral->referred_account_id,
                'side' => ReferralCreditSide::Referred,
                'percent' => $percent,
                'remaining_percent' => $percent,
                'status' => ReferralCreditStatus::Available,
                'first_payment_only' => true,
                'available_at' => now(),
            ]);
        });

        return true;
    }

    protected function consumeCredits(ReferralAccount $account): int
    {
        $firstPaymentDone = $account->first_paid_period_at !== null
            || $account->business?->first_paid_period_at !== null;
        $cap = max(1, (int) config('kospal.referral.max_percent_per_payment', 100));
        $applied = 0;

        $credits = ReferralCredit::query()
            ->spendable()
            ->where('referral_account_id', $account->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($credits as $credit) {
            if ($applied >= $cap) {
                break;
            }

            if ($credit->first_payment_only && $firstPaymentDone) {
                continue;
            }

            $take = min($credit->remaining_percent, $cap - $applied);
            $remaining = $credit->remaining_percent - $take;

            $credit->forceFill([
                'remaining_percent' => $remaining,
                'status' => $remaining === 0
                    ? ReferralCreditStatus::Consumed
                    : ReferralCreditStatus::Available,
                'applied_at' => now(),
            ])->save();

            $applied += $take;
        }

        return $applied;
    }

    protected function availablePercentFor(ReferralAccount $account): int
    {
        $firstPaymentDone = $account->first_paid_period_at !== null
            || $account->business?->first_paid_period_at !== null;

        return (int) ReferralCredit::query()
            ->spendable()
            ->where('referral_account_id', $account->id)
            ->when(
                $firstPaymentDone,
                fn ($query) => $query->where('first_payment_only', false),
            )
            ->sum('remaining_percent');
    }

    protected function assertNotSelfReferral(ReferralAccount $referrer, ReferralAccount $referred, ?User $owner): void
    {
        if ($referrer->id === $referred->id || $referrer->public_uuid === $referred->public_uuid) {
            throw ValidationException::withMessages([
                'referral_code' => 'You cannot use your own invite.',
            ]);
        }

        $ownerEmail = ReferralAccount::normalizeEmail($owner?->email ?: $referred->owner_email);

        if ($ownerEmail !== '' && $ownerEmail === $referrer->owner_email) {
            throw ValidationException::withMessages([
                'referral_code' => 'You cannot invite yourself.',
            ]);
        }

        if (
            $referrer->owner_user_id !== null
            && $referred->owner_user_id !== null
            && $referrer->owner_user_id === $referred->owner_user_id
        ) {
            throw ValidationException::withMessages([
                'referral_code' => 'You cannot invite yourself.',
            ]);
        }

        if (
            filled($referrer->machine_id)
            && filled($referred->machine_id)
            && hash_equals((string) $referrer->machine_id, (string) $referred->machine_id)
        ) {
            throw ValidationException::withMessages([
                'referral_code' => 'This invite cannot be used on the same device.',
            ]);
        }
    }

    protected function uniqueCode(): string
    {
        do {
            $code = 'KSP-'.Str::upper(Str::random(6));
        } while (ReferralCode::query()->where('code', $code)->exists());

        return $code;
    }

    protected function normalizeCode(?string $code): ?string
    {
        if (! is_string($code)) {
            return null;
        }

        $normalized = strtoupper(trim($code));

        return $normalized === '' ? null : $normalized;
    }

    protected function localMachineId(): ?string
    {
        if (! Deployment::isDesktop()) {
            return null;
        }

        try {
            return $this->machineId->get();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function codeExpiresDays(): int
    {
        return max(1, (int) config('kospal.referral.code_expires_days', 3));
    }

    protected function qualifyAfterDays(): int
    {
        return max(1, (int) config('kospal.referral.qualify_after_days', 7));
    }
}
