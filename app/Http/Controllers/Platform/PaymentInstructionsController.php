<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePaymentInstructionsRequest;
use App\Models\PlatformSetting;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentInstructionsController extends Controller
{
    public function edit(): Response
    {
        $this->authorize('viewAny', PlatformSetting::class);

        return Inertia::render('platform/payment-instructions/edit', [
            'instructions' => PlatformSetting::paymentInstructions(),
        ]);
    }

    public function update(
        UpdatePaymentInstructionsRequest $request,
        AuditLogger $audit,
    ): RedirectResponse {
        PlatformSetting::setPaymentInstructions($request->validated());

        $audit->log(
            action: 'platform.payment_instructions.updated',
            metadata: $request->validated(),
            actor: $request->user(),
            businessId: null,
        );

        return back()->with('success', 'Payment instructions updated.');
    }
}
