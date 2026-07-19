<?php

namespace App\Http\Requests\Concerns;

use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

trait ConvertsMoneyFields
{
    /**
     * @param  list<string>  $fields
     * @return array<string, int|null>
     */
    protected function moneyFieldsToMinor(array $fields): array
    {
        $currency = app(TenantContext::class)->business()?->currency;

        if ($currency === null) {
            throw ValidationException::withMessages([
                'currency' => 'Business currency is required to save money amounts.',
            ]);
        }

        $converted = [];

        foreach ($fields as $field) {
            if (! $this->exists($field) || $this->input($field) === null || $this->input($field) === '') {
                continue;
            }

            try {
                $converted[$field] = Money::toMinor($this->input($field), $currency);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    $field => 'Enter a valid amount.',
                ]);
            }
        }

        return $converted;
    }
}
