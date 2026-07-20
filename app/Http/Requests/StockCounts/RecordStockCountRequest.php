<?php

namespace App\Http\Requests\StockCounts;

use Illuminate\Foundation\Http\FormRequest;

class RecordStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $count = $this->route('stockCount');

        return $this->user()?->can('update', $count) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'counts' => ['required', 'array', 'min:1'],
            'counts.*.product_id' => ['required', 'integer'],
            'counts.*.counted_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
