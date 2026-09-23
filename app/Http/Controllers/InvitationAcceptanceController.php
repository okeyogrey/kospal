<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Models\Invitation;
use App\Models\User;
use App\Services\StaffInvitationService;
use App\Support\Tenancy\ResolvesTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationAcceptanceController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invitation = Invitation::query()
            ->with('business:id,name')
            ->where('token', $token)
            ->firstOrFail();

        $accountExists = User::query()
            ->where('email', $invitation->email)
            ->exists();

        $user = $request->user();
        $emailMatches = $user !== null
            && strcasecmp($user->email, $invitation->email) === 0;

        if ($user === null) {
            $request->session()->put(
                'url.intended',
                route('invitations.accept.show', $invitation->token),
            );
        }

        return Inertia::render('invitations/accept', [
            'invitation' => [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'business_name' => $invitation->business?->name,
                'expires_at' => $invitation->expires_at,
                'is_acceptable' => $invitation->isAcceptable(),
            ],
            'account_exists' => $accountExists,
            'email_matches' => $emailMatches,
        ]);
    }

    public function accept(
        Request $request,
        string $token,
        StaffInvitationService $staff,
        ResolvesTenant $resolver,
    ): RedirectResponse {
        $invitation = Invitation::query()->where('token', $token)->firstOrFail();

        $staff->accept($invitation, $request->user());
        $resolver->resolve($request->user()->fresh());

        return redirect()
            ->route('dashboard')
            ->with('success', 'You have joined the business.');
    }

    public function register(
        Request $request,
        string $token,
        StaffInvitationService $staff,
        ResolvesTenant $resolver,
    ): RedirectResponse {
        if ($request->user()) {
            throw ValidationException::withMessages([
                'invitation' => 'Sign out first to create an account for this invitation.',
            ]);
        }

        $invitation = Invitation::query()->where('token', $token)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ]);

        $membership = $staff->registerAndAccept($invitation, [
            'name' => $validated['name'],
            'password' => $validated['password'],
        ]);

        $user = User::query()->findOrFail($membership->user_id);
        Auth::login($user);
        $request->session()->regenerate();
        $resolver->resolve($user->fresh());

        return redirect()
            ->route('dashboard')
            ->with('success', 'Your account is ready. Welcome to the team.');
    }
}
