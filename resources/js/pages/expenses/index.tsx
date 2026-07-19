import { Head, Link, router, useForm } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import { formatMoney } from '@/lib/money';
import { index as categoriesIndex } from '@/routes/expense-categories';
import {
    create as createExpense,
    index as expensesIndex,
    show as showExpense,
} from '@/routes/expenses';

type ExpenseRow = {
    id: number;
    branch_name: string | null;
    category_name: string | null;
    expense_date: string | null;
    currency: string;
    amount: number;
    amount_formatted: string;
    payee: string;
    description: string | null;
    created_by_name: string | null;
    has_receipt: boolean;
};

type Option = { id: number; name: string; is_active?: boolean };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

export default function ExpensesIndex({
    expenses,
    branches,
    categories,
    filters,
    permissions,
    currency,
}: {
    expenses: Paginated<ExpenseRow>;
    branches: Option[];
    categories: Option[];
    filters: {
        search: string;
        branch_id: number | null;
        expense_category_id: number | null;
        date_from: string | null;
        date_to: string | null;
    };
    permissions: {
        create: boolean;
        manage_categories: boolean;
    };
    currency: string;
}) {
    const { t, locale } = useTranslations();

    return (
        <>
            <Head title={t('pages.expenses.title')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            {t('pages.expenses.title')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('pages.expenses.description')}
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        {permissions.manage_categories && (
                            <Button
                                variant="outline"
                                asChild
                                className="w-full sm:w-auto"
                            >
                                <Link href={categoriesIndex()}>
                                    {t('pages.expenses.categories')}
                                </Link>
                            </Button>
                        )}
                        {permissions.create && (
                            <Button asChild className="w-full sm:w-auto">
                                <Link href={createExpense()}>
                                    {t('pages.expenses.add')}
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <form
                    className="grid gap-2 sm:grid-cols-2 lg:grid-cols-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.get(
                            expensesIndex.url({
                                query: {
                                    search:
                                        String(data.get('search') ?? '') ||
                                        undefined,
                                    branch_id:
                                        String(data.get('branch_id') ?? '') ||
                                        undefined,
                                    expense_category_id:
                                        String(
                                            data.get('expense_category_id') ??
                                                '',
                                        ) || undefined,
                                    date_from:
                                        String(data.get('date_from') ?? '') ||
                                        undefined,
                                    date_to:
                                        String(data.get('date_to') ?? '') ||
                                        undefined,
                                },
                            }),
                            {},
                            { preserveState: true },
                        );
                    }}
                >
                    <Input
                        name="search"
                        placeholder={t('pages.expenses.search_placeholder')}
                        defaultValue={filters.search}
                        className="lg:col-span-2"
                    />
                    <select
                        name="branch_id"
                        defaultValue={filters.branch_id ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">
                            {t('pages.expenses.all_branches')}
                        </option>
                        {branches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                {branch.name}
                            </option>
                        ))}
                    </select>
                    <select
                        name="expense_category_id"
                        defaultValue={filters.expense_category_id ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">
                            {t('pages.expenses.all_categories')}
                        </option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                    <Input
                        type="date"
                        name="date_from"
                        defaultValue={filters.date_from ?? ''}
                        aria-label={t('pages.expenses.date_from')}
                    />
                    <div className="flex gap-2">
                        <Input
                            type="date"
                            name="date_to"
                            defaultValue={filters.date_to ?? ''}
                            aria-label={t('pages.expenses.date_to')}
                            className="flex-1"
                        />
                        <Button type="submit" variant="outline">
                            {t('pages.expenses.filter')}
                        </Button>
                    </div>
                </form>

                {expenses.data.length === 0 ? (
                    <EmptyState
                        icon={<Receipt className="size-5" />}
                        title={t('pages.expenses.empty_title')}
                        description={t('pages.expenses.empty_description')}
                    />
                ) : (
                    <div className="space-y-3">
                        {expenses.data.map((expense) => (
                            <Link
                                key={expense.id}
                                href={showExpense(expense)}
                                className="block rounded-lg border border-border/80 px-4 py-3 transition hover:bg-muted/40"
                            >
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {expense.payee}
                                            </span>
                                            {expense.has_receipt && (
                                                <Badge variant="secondary">
                                                    {t(
                                                        'pages.expenses.receipt',
                                                    )}
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {expense.category_name ?? '—'} ·{' '}
                                            {expense.branch_name ?? '—'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {expense.expense_date}
                                            {expense.created_by_name
                                                ? ` · ${expense.created_by_name}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-medium tabular-nums">
                                            {expense.amount_formatted ||
                                                formatMoney(
                                                    expense.amount,
                                                    expense.currency ||
                                                        currency,
                                                    locale,
                                                )}
                                        </p>
                                    </div>
                                </div>
                            </Link>
                        ))}
                        <Pagination links={expenses.links} />
                    </div>
                )}
            </div>
        </>
    );
}

ExpensesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Expenses',
            href: expensesIndex(),
        },
    ],
};
