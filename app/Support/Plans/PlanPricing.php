<?php

namespace App\Support\Plans;

use App\Enums\Plan;
use App\Support\Money\CurrencyConverter;
use App\Support\Money\Money;
use InvalidArgumentException;

final class PlanPricing
{
    public static function monthlyMajor(Plan $plan, string $currency): int|float
    {
        $currency = strtoupper($currency);

        if ($currency === 'BIF') {
            return CurrencyConverter::kesMajorToBif(self::listedMajor($plan, 'KES'));
        }

        return self::listedMajor($plan, $currency);
    }

    public static function monthlyMinor(Plan $plan, string $currency): int
    {
        $currency = strtoupper($currency);

        return Money::toMinor(self::monthlyMajor($plan, $currency), $currency);
    }

    public static function formatMonthly(Plan $plan, string $currency, ?string $locale = null): string
    {
        return Money::format(self::monthlyMinor($plan, $currency), $currency, $locale);
    }

    /**
     * @return array{
     *     currency: string,
     *     list_minor: int,
     *     list_formatted: string,
     *     discount_percent: int,
     *     discount_minor: int,
     *     due_minor: int,
     *     due_formatted: string,
     * }
     */
    public static function quote(Plan $plan, string $currency, int $discountPercent = 0): array
    {
        $currency = strtoupper($currency);
        $list = self::monthlyMinor($plan, $currency);
        $percent = max(0, min(100, $discountPercent));
        $discount = intdiv($list * $percent, 100);
        $due = $list - $discount;

        return [
            'currency' => $currency,
            'list_minor' => $list,
            'list_formatted' => Money::format($list, $currency),
            'discount_percent' => $percent,
            'discount_minor' => $discount,
            'due_minor' => $due,
            'due_formatted' => Money::format($due, $currency),
        ];
    }

    private static function listedMajor(Plan $plan, string $currency): int|float
    {
        $price = config('kospal.plans.'.$plan->value.'.prices.'.$currency);

        if (! is_numeric($price)) {
            throw new InvalidArgumentException("No {$currency} price configured for [{$plan->value}].");
        }

        return $price + 0;
    }
}
