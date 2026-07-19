<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateBusinessSubscriptionRequest;
use App\Models\Business;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;

class BusinessSubscriptionController extends Controller
{
    public function update(
        UpdateBusinessSubscriptionRequest $request,
        Business $business,
        SubscriptionService $subscriptions,
    ): RedirectResponse {
        $subscriptions->updateBusinessSubscription(
            $business,
            $request->user(),
            $request->validated(),
        );

        return back()->with('success', 'Business subscription updated.');
    }
}
