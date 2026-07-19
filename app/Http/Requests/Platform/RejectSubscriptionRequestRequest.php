<?php

namespace App\Http\Requests\Platform;

use App\Models\SubscriptionRequest;
use Illuminate\Foundation\Http\FormRequest;

class RejectSubscriptionRequestRequest extends FormRequest
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
            'reviewer_notes' => ['required', 'string', 'max:2000'],
        ];
    }
}
