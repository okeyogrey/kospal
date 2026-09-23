<?php

namespace App\Http\Controllers;

use App\Http\Requests\Onboarding\StoreBusinessOnboardingRequest;
use App\Services\BusinessOnboardingService;
use App\Services\ReferralService;
use App\Support\Deployment;
use App\Support\Plans\PlanCatalog;
use App\Support\Tenancy\ResolvesTenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        $user = request()->user();

        if ($user?->isPlatformSuperAdmin() && Deployment::isWeb()) {
            return redirect()->route('platform.subscription-requests.index');
        }

        if ($user?->memberships()->where('is_active', true)->exists()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('onboarding/create', [
            'countries' => config('kospal.countries'),
            'currencies' => config('kospal.currencies'),
            'plans' => PlanCatalog::cards(null, 'USD'),
            'default_trial_edition' => (string) config('deployment.license.default_edition', 'pro'),
            'trial_days' => (int) config('deployment.license.trial_days', 60),
            'referral' => app(ReferralService::class)->previewOrNull(
                app(ReferralService::class)->capturedCode(),
            ),
        ]);
    }

    public function store(
        StoreBusinessOnboardingRequest $request,
        BusinessOnboardingService $onboarding,
        ResolvesTenant $resolver,
    ): RedirectResponse {
        $onboarding->onboard($request->user(), $request->validated());
        $resolver->resolve($request->user()->fresh());

        return redirect()
            ->route('dashboard')
            ->with('success', 'Your business is ready.');
    }
}
