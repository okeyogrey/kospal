<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Services\StaffInvitationService;
use App\Support\Tenancy\ResolvesTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvitationAcceptanceController extends Controller
{
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = Invitation::query()
            ->with('business:id,name')
            ->where('token', $token)
            ->firstOrFail();

        return Inertia::render('invitations/accept', [
            'invitation' => [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'business_name' => $invitation->business?->name,
                'expires_at' => $invitation->expires_at,
                'is_acceptable' => $invitation->isAcceptable(),
            ],
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
}
