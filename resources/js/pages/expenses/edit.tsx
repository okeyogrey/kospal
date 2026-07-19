import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import {
    index as expensesIndex,
    show as showExpense,
    update,
} from '@/routes/expenses';

type Option = { id: number; name: string; is_active?: boolean };

type Receipt = {
    id: number;
    original_name: string;
    mime_type: string;
    size: number;
    download_url: string;
};

type ExpenseDetail = {
    id: number;
    branch_id: number;
    expense_category_id: number;
    expense_date: string | null;
    amount_input: string;
    payee: string;
    description: string | null;
    receipt: Receipt | null;
};

export default function ExpensesEdit({
    expense,
    branches,
    categories,
    currency,
    attachmentLimits,
}: {
    expense: ExpenseDetail;
    branches: Option[];
    categories: Option[];
    currency: string;
    attachmentLimits: {
        max_kilobytes: number;
        allowed_mimes: string[];
    };
}) {
    const { t } = useTranslations();
    const form = useForm<{
        branch_id: string;
        expense_category_id: string;
        expense_date: string;
        amount: string;
        payee: string;
        description: string;
        receipt: File | null;
        remove_receipt: boolean;
    }>({
        branch_id: String(expense.branch_id),
        expense_category_id: String(expense.expense_category_id),
        expense_date: expense.expense_date ?? '',
        amount: expense.amount_input,
        payee: expense.payee,
        description: expense.description ?? '',
        receipt: null,
        remove_receipt: false,
    });

    const accept = attachmentLimits.allowed_mimes
        .map((mime) => (mime === 'jpg' ? '.jpg,.jpeg' : `.${mime}`))
        .join(',');

    return (
        <>
            <Head title={t('pages.expenses.edit_title')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            {t('pages.expenses.edit_title')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {expense.payee}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        asChild
                        className="w-full sm:w-auto"
                    >
                        <Link href={showExpense(expense)}>
                            {t('pages.expenses.cancel')}
                        </Link>
                    </Button>
                </div>

                <form
                    className="mx-auto w-full max-w-2xl space-y-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(update.url(expense), {
                            forceFormData: true,
                        });
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="branch_id">
                                {t('pages.expenses.branch')}
                            </Label>
                            <select
                                id="branch_id"
                                value={form.data.branch_id}
                                onChange={(event) =>
                                    form.setData(
                                        'branch_id',
                                        event.target.value,
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                required
                            >
                                {branches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>
                                        {branch.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.branch_id} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="expense_category_id">
                                {t('pages.expenses.category')}
                            </Label>
                            <select
                                id="expense_category_id"
                                value={form.data.expense_category_id}
                                onChange={(event) =>
                                    form.setData(
                                        'expense_category_id',
                                        event.target.value,
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                required
                            >
                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.id}
                                    >
                                        {category.name}
                                        {category.is_active === false
                                            ? ' (inactive)'
                                            : ''}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={form.errors.expense_category_id}
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="expense_date">
                                {t('pages.expenses.date')}
                            </Label>
                            <Input
                                id="expense_date"
                                type="date"
                                value={form.data.expense_date}
                                onChange={(event) =>
                                    form.setData(
                                        'expense_date',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <InputError message={form.errors.expense_date} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="amount">
                                {t('pages.expenses.amount')} ({currency})
                            </Label>
                            <Input
                                id="amount"
                                inputMode="decimal"
                                value={form.data.amount}
                                onChange={(event) =>
                                    form.setData('amount', event.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.amount} />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="payee">
                            {t('pages.expenses.payee')}
                        </Label>
                        <Input
                            id="payee"
                            value={form.data.payee}
                            onChange={(event) =>
                                form.setData('payee', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.payee} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="description">
                            {t('pages.expenses.description_field')}
                        </Label>
                        <textarea
                            id="description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            rows={3}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <div className="space-y-3 rounded-md border border-border/80 p-4">
                        <Label>{t('pages.expenses.receipt')}</Label>
                        {expense.receipt && !form.data.remove_receipt ? (
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <span className="truncate">
                                    {expense.receipt.original_name}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        form.setData('remove_receipt', true)
                                    }
                                >
                                    {t('pages.expenses.remove_receipt')}
                                </Button>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {t('pages.expenses.no_receipt')}
                            </p>
                        )}
                        <Input
                            type="file"
                            accept={accept}
                            onChange={(event) => {
                                form.setData(
                                    'receipt',
                                    event.target.files?.[0] ?? null,
                                );
                                if (event.target.files?.[0]) {
                                    form.setData('remove_receipt', false);
                                }
                            }}
                        />
                        <p className="text-xs text-muted-foreground">
                            {`PDF or image, max ${attachmentLimits.max_kilobytes} KB`}
                        </p>
                        <InputError message={form.errors.receipt} />
                    </div>

                    <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            asChild
                            className="w-full sm:w-auto"
                        >
                            <Link href={expensesIndex()}>
                                {t('pages.expenses.back')}
                            </Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="w-full sm:w-auto"
                        >
                            {form.processing
                                ? t('pages.expenses.saving')
                                : t('pages.expenses.save_changes')}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

ExpensesEdit.layout = {
    breadcrumbs: [
        {
            title: 'Expenses',
            href: expensesIndex(),
        },
        {
            title: 'Edit',
            href: '#',
        },
    ],
};
