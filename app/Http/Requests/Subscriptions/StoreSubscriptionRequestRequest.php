<?php

namespace App\Http\Requests\Subscriptions;

use App\Enums\Plan;
use App\Models\SubscriptionRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = app(TenantContext::class)->business();

        return $business !== null
            && ($this->user()?->can('manageSubscription', $business) ?? false)
            && ($this->user()?->can('create', SubscriptionRequest::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'requested_plan' => ['required', 'string', Rule::in(Plan::values())],
            'transaction_code' => ['required', 'string', 'min:4', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
