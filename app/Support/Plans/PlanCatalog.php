<?php

namespace App\Support\Plans;

use App\Enums\Plan;

final class PlanCatalog
{
    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     description: string,
     *     max_branches: int,
     *     max_staff: int|null,
     *     features: list<string>,
     *     is_current: bool,
     *     change_type: string|null,
     *     currency: string,
     *     price_minor: int,
     *     price_formatted: string,
     *     due_minor: int,
     *     due_formatted: string,
     *     discount_percent: int,
     * }>
     */
    public static function cards(?Plan $current = null, ?string $currency = null, int $discountPercent = 0): array
    {
        $currency = strtoupper($currency ?: 'USD');
        $percent = max(0, min(100, $discountPercent));

        return collect(config('kospal.plans'))
            ->map(function (array $plan, string $key) use ($current, $currency, $percent) {
                /** @var array{name: string, description?: string, max_branches: int, max_staff: int|null, features: list<string>} $plan */
                $edition = Plan::from($key);
                $quote = PlanPricing::quote($edition, $currency, $percent);

                return [
                    'key' => $key,
                    'name' => $plan['name'],
                    'description' => $plan['description'] ?? '',
                    'max_branches' => $plan['max_branches'],
                    'max_staff' => $plan['max_staff'],
                    'features' => $plan['features'],
                    'is_current' => $current?->value === $key,
                    'change_type' => $current?->changeTypeToward($edition)->value,
                    'currency' => $currency,
                    'price_minor' => $quote['list_minor'],
                    'price_formatted' => $quote['list_formatted'],
                    'due_minor' => $quote['due_minor'],
                    'due_formatted' => $quote['due_formatted'],
                    'discount_percent' => $percent,
                ];
            })
            ->values()
            ->all();
    }
}
