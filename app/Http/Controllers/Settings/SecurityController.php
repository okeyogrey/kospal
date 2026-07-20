<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Http\Requests\Settings\UpdateApprovalPinRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request, TenantContext $tenant): Response
    {
        $membership = $tenant->membership();
        $role = $tenant->role();

        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'canManageApprovalPin' => $role?->canApplySaleDiscount() ?? false,
            'hasApprovalPin' => $membership?->hasApprovalPin() ?? false,
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }

    /**
     * Set or clear the manager approval PIN for the current business membership.
     */
    public function updateApprovalPin(
        UpdateApprovalPinRequest $request,
        TenantContext $tenant,
    ): RedirectResponse {
        $membership = $tenant->membership();
        abort_unless($membership !== null, 403);

        if ($request->boolean('clear_pin')) {
            $membership->forceFill(['approval_pin' => null])->save();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Manager PIN cleared.')]);

            return back();
        }

        $membership->forceFill([
            'approval_pin' => $request->input('pin'),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Manager PIN updated.')]);

        return back();
    }
}
