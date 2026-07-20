<?php

namespace App\Services;

use App\Contracts\FeatureFlagService;
use App\Enums\SaleStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Support\FeatureFlags\Features;
use Illuminate\Validation\ValidationException;

class CustomerCreditService
{
    public function __construct(
        protected FeatureFlagService $limits,
    ) {}

    public function assertFeature(Business $business): void
    {
        $this->limits->assertHasFeature($business, Features::CUSTOMER_CREDIT);
    }

    public function outstandingBalance(Customer $customer): int
    {
        $latestBalance = $customer->ledgerEntries()
            ->orderByDesc('id')
            ->value('balance_after');

        if ($latestBalance !== null) {
            return max(0, (int) $latestBalance);
        }

        return (int) $customer->sales()
            ->where('status', SaleStatus::Completed)
            ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as due')
            ->value('due');
    }

    public function creditAvailable(Customer $customer): ?int
    {
        if (! $customer->credit_enabled) {
            return 0;
        }

        if ($customer->credit_limit === null) {
            return null;
        }

        return max(0, $customer->credit_limit - $this->outstandingBalance($customer));
    }

    public function assertCanChargeCredit(Customer $customer, int $creditAmount): void
    {
        if ($creditAmount < 1) {
            return;
        }

        $this->assertFeature($customer->business);

        if (! $customer->credit_enabled) {
            throw ValidationException::withMessages([
                'payments' => 'This customer does not have credit enabled.',
            ]);
        }

        if ($customer->credit_limit === null) {
            return;
        }

        $available = $this->creditAvailable($customer);

        if ($creditAmount > ($available ?? 0)) {
            throw ValidationException::withMessages([
                'payments' => sprintf(
                    'Credit amount exceeds available credit limit (%d remaining).',
                    $available ?? 0,
                ),
            ]);
        }
    }
}
