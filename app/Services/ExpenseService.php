<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Expense;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        protected AuditLogger $audit,
        protected AttachmentService $attachments,
    ) {}

    /**
     * @param  array{
     *     branch_id: int,
     *     expense_category_id: int,
     *     expense_date: string,
     *     amount: int,
     *     payee: string,
     *     description?: string|null,
     * }  $data
     */
    public function create(
        Business $business,
        array $data,
        User $actor,
        ?UploadedFile $receipt = null,
    ): Expense {
        return DB::transaction(function () use ($business, $data, $actor, $receipt): Expense {
            $expense = Expense::query()->create([
                'business_id' => $business->id,
                'branch_id' => $data['branch_id'],
                'expense_category_id' => $data['expense_category_id'],
                'created_by' => $actor->id,
                'expense_date' => $data['expense_date'],
                'currency' => $business->currency,
                'amount' => $data['amount'],
                'payee' => $data['payee'],
                'description' => $data['description'] ?? null,
            ]);

            if ($receipt !== null) {
                $this->attachments->storeForExpense($expense, $receipt, $actor);
            }

            $this->audit->log(
                action: 'expense.created',
                auditable: $expense,
                metadata: [
                    'branch_id' => $expense->branch_id,
                    'expense_category_id' => $expense->expense_category_id,
                    'amount' => $expense->amount,
                    'payee' => $expense->payee,
                ],
                actor: $actor,
                businessId: $business->id,
            );

            return $expense->fresh(['branch', 'category', 'creator', 'receipt']) ?? $expense;
        });
    }

    /**
     * @param  array{
     *     branch_id?: int,
     *     expense_category_id?: int,
     *     expense_date?: string,
     *     amount?: int,
     *     payee?: string,
     *     description?: string|null,
     *     remove_receipt?: bool,
     * }  $data
     */
    public function update(
        Expense $expense,
        array $data,
        User $actor,
        ?UploadedFile $receipt = null,
    ): Expense {
        return DB::transaction(function () use ($expense, $data, $actor, $receipt): Expense {
            $removeReceipt = (bool) ($data['remove_receipt'] ?? false);
            unset($data['remove_receipt']);

            $expense->update($data);

            if ($removeReceipt && $receipt === null) {
                foreach ($expense->attachments()->get() as $attachment) {
                    $this->attachments->delete($attachment, $actor);
                }
            }

            if ($receipt !== null) {
                $this->attachments->replaceExpenseReceipt($expense, $receipt, $actor);
            }

            $this->audit->log(
                action: 'expense.updated',
                auditable: $expense,
                metadata: $data,
                actor: $actor,
                businessId: $expense->business_id,
            );

            return $expense->fresh(['branch', 'category', 'creator', 'receipt']) ?? $expense;
        });
    }

    public function delete(Expense $expense, User $actor): void
    {
        DB::transaction(function () use ($expense, $actor): void {
            $businessId = $expense->business_id;
            $payload = [
                'expense_id' => $expense->id,
                'branch_id' => $expense->branch_id,
                'amount' => $expense->amount,
                'payee' => $expense->payee,
            ];

            $this->attachments->deleteAllFor($expense, $actor);
            $expense->delete();

            $this->audit->log(
                action: 'expense.deleted',
                metadata: $payload,
                actor: $actor,
                businessId: $businessId,
            );
        });
    }
}
