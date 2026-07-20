<?php

namespace App\Services;

use App\Enums\CustomerLedgerEntryType;
use App\Enums\LedgerDirection;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CustomerLedgerService
{
    public function currentBalance(Customer $customer): int
    {
        $balance = $customer->ledgerEntries()
            ->orderByDesc('id')
            ->value('balance_after');

        return max(0, (int) ($balance ?? 0));
    }

    /**
     * @return list<array{
     *     id: int,
     *     type: string,
     *     type_label: string,
     *     direction: string,
     *     amount: int,
     *     balance_after: int,
     *     description: string,
     *     entry_date: string,
     *     reference_type: string|null,
     *     reference_id: int|null,
     * }>
     */
    public function entriesForPeriod(
        Customer $customer,
        ?string $from = null,
        ?string $to = null,
        int $limit = 100,
    ): array {
        return CustomerLedgerEntry::query()
            ->where('customer_id', $customer->id)
            ->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (CustomerLedgerEntry $entry) => [
                'id' => $entry->id,
                'type' => $entry->type->value,
                'type_label' => $entry->type->label(),
                'direction' => $entry->direction->value,
                'amount' => $entry->amount,
                'balance_after' => $entry->balance_after,
                'description' => $entry->description,
                'entry_date' => $entry->entry_date?->toDateString() ?? '',
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
            ])
            ->values()
            ->all();
    }

    public function record(
        Business $business,
        Customer $customer,
        CustomerLedgerEntryType $type,
        LedgerDirection $direction,
        int $amount,
        string $description,
        ?Model $reference = null,
        ?User $actor = null,
        ?string $entryDate = null,
    ): CustomerLedgerEntry {
        return DB::transaction(function () use (
            $business,
            $customer,
            $type,
            $direction,
            $amount,
            $description,
            $reference,
            $actor,
            $entryDate,
        ): CustomerLedgerEntry {
            $previous = CustomerLedgerEntry::query()
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('balance_after');

            $balanceAfter = max(0, (int) ($previous ?? 0) + $direction->signedAmount($amount));

            return CustomerLedgerEntry::query()->create([
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'type' => $type,
                'direction' => $direction,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'entry_date' => $entryDate ?? now()->toDateString(),
                'created_by' => $actor?->id,
                'created_at' => now(),
            ]);
        });
    }
}
