<?php

namespace App\Http\Requests\Sales;

use App\Enums\PaymentMethod;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartialReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Sale $sale */
        $sale = $this->route('sale');

        return $this->user()?->can('returnItems', $sale) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'refund_method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'client_request_id' => ['required', 'string', 'uuid', 'max:64'],
            'manager_approval' => ['nullable', 'array'],
            'manager_approval.pin' => ['nullable', 'string', 'max:8'],
            'manager_approval.login' => ['nullable', 'string', 'max:255'],
            'manager_approval.password' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        if ($key !== null) {
            return $data;
        }

        return [
            ...$data,
            'manager_approval' => $data['manager_approval'] ?? null,
            'items' => collect($data['items'] ?? [])->map(fn (array $item): array => [
                'sale_item_id' => (int) $item['sale_item_id'],
                'quantity' => (int) $item['quantity'],
            ])->values()->all(),
        ];
    }
}
