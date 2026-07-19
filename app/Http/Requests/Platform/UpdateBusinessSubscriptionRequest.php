<?php

namespace App\Http\Requests\Platform;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Business $business */
        $business = $this->route('business');

        return $this->user()?->can('administerSubscription', $business) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', Rule::in(Plan::values())],
            'subscription_status' => ['required', 'string', Rule::in(SubscriptionStatus::values())],
            'subscription_ends_at' => ['nullable', 'date'],
            'reviewer_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
