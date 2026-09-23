<?php

namespace App\Http\Controllers;

use App\Services\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ReferralLandingController extends Controller
{
    public function __invoke(string $code, ReferralService $referrals): RedirectResponse
    {
        $referrals->captureCode($code);

        try {
            $preview = $referrals->preview($code);
        } catch (ValidationException $e) {
            return redirect()
                ->route('register')
                ->with('error', $e->getMessage());
        }

        if (! $preview['usable']) {
            return redirect()
                ->route('register')
                ->with('error', 'This invite has expired. Ask for a new link.');
        }

        return redirect()
            ->route('register')
            ->with('success', "Invite from {$preview['referrer_name']} applied. Finish setup to start your trial.");
    }
}
