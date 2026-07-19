<?php

namespace App\Http\Requests\Platform;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\SubscriptionRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveSubscriptionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SubscriptionRequest $subscriptionRequest */
        $subscriptionRequest = $this->route('subscriptionRequest');

        return $this->user()?->can('review', $subscriptionRequest) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan' => ['nullable', 'string', Rule::in(Plan::values())],
            'subscription_status' => ['nullable', 'string', Rule::in(SubscriptionStatus::values())],
            'subscription_ends_at' => ['nullable', 'date'],
            'reviewer_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
