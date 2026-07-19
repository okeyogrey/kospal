import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import { index as expensesIndex, store } from '@/routes/expenses';

type Option = { id: number; name: string };

export default function ExpensesCreate({
    branches,
    categories,
    defaultBranchId,
    currency,
    attachmentLimits,
}: {
    branches: Option[];
    categories: Option[];
    defaultBranchId: number | null;
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
    }>({
        branch_id: String(defaultBranchId ?? branches[0]?.id ?? ''),
        expense_category_id: String(categories[0]?.id ?? ''),
        expense_date: new Date().toISOString().slice(0, 10),
        amount: '',
        payee: '',
        description: '',
        receipt: null,
    });

    const accept = attachmentLimits.allowed_mimes
        .map((mime) => (mime === 'jpg' ? '.jpg,.jpeg' : `.${mime}`))
        .join(',');

    return (
        <>
            <Head title={t('pages.expenses.create_title')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            {t('pages.expenses.create_title')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('pages.expenses.create_description')}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        asChild
                        className="w-full sm:w-auto"
                    >
                        <Link href={expensesIndex()}>
                            {t('pages.expenses.back')}
                        </Link>
                    </Button>
                </div>

                {categories.length === 0 ? (
                    <p className="rounded-md border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                        {t('pages.expenses.need_category')}
                    </p>
                ) : (
                    <form
                        className="mx-auto w-full max-w-2xl space-y-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(store.url(), {
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
                                    <option value="">
                                        {t('pages.expenses.select_branch')}
                                    </option>
                                    {branches.map((branch) => (
                                        <option
                                            key={branch.id}
                                            value={branch.id}
                                        >
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
                                    <option value="">
                                        {t('pages.expenses.select_category')}
                                    </option>
                                    {categories.map((category) => (
                                        <option
                                            key={category.id}
                                            value={category.id}
                                        >
                                            {category.name}
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
                                <InputError
                                    message={form.errors.expense_date}
                                />
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
                                        form.setData(
                                            'amount',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="0.00"
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
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                rows={3}
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            />
                            <InputError message={form.errors.description} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="receipt">
                                {t('pages.expenses.receipt_optional')}
                            </Label>
                            <Input
                                id="receipt"
                                type="file"
                                accept={accept}
                                onChange={(event) =>
                                    form.setData(
                                        'receipt',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {t('pages.expenses.receipt_hint')}
                            </p>
                            <InputError message={form.errors.receipt} />
                        </div>

                        <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                            <Button
                                type="submit"
                                disabled={
                                    form.processing || categories.length === 0
                                }
                                className="w-full sm:w-auto"
                            >
                                {form.processing
                                    ? t('pages.expenses.saving')
                                    : t('pages.expenses.save')}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

ExpensesCreate.layout = {
    breadcrumbs: [
        {
            title: 'Expenses',
            href: expensesIndex(),
        },
        {
            title: 'Create',
            href: '#',
        },
    ],
};
