import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { formatMoney } from '@/lib/money';
import {
    destroy,
    edit as editExpense,
    index as expensesIndex,
} from '@/routes/expenses';

type Receipt = {
    id: number;
    original_name: string;
    mime_type: string;
    size: number;
    download_url: string;
};

type ExpenseDetail = {
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
    created_at: string | null;
    receipt: Receipt | null;
};

type ActivityRow = {
    id: number;
    action: string;
    user_name: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
};

function formatWhen(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function ExpensesShow({
    expense,
    activity,
    permissions,
    currency,
}: {
    expense: ExpenseDetail;
    activity: ActivityRow[];
    permissions: {
        update: boolean;
        delete: boolean;
    };
    currency: string;
}) {
    const { t, locale } = useTranslations();

    const remove = () => {
        if (!window.confirm(t('pages.expenses.confirm_delete'))) {
            return;
        }

        router.delete(destroy.url(expense));
    };

    return (
        <>
            <Head title={expense.payee} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2">
                        <h1 className="font-display text-2xl font-semibold">
                            {expense.payee}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {expense.category_name ?? '—'} ·{' '}
                            {expense.branch_name ?? '—'}
                        </p>
                        <p className="text-lg font-medium tabular-nums">
                            {expense.amount_formatted ||
                                formatMoney(
                                    expense.amount,
                                    expense.currency || currency,
                                    locale,
                                )}
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <Button variant="outline" asChild>
                            <Link href={expensesIndex()}>
                                {t('pages.expenses.back')}
                            </Link>
                        </Button>
                        {permissions.update && (
                            <Button asChild>
                                <Link href={editExpense(expense)}>
                                    {t('pages.expenses.edit')}
                                </Link>
                            </Button>
                        )}
                        {permissions.delete && (
                            <Button variant="destructive" onClick={remove}>
                                {t('pages.expenses.delete')}
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-[1.2fr_1fr]">
                    <div className="space-y-6">
                        <section className="space-y-3">
                            <h2 className="text-sm font-medium">
                                {t('pages.expenses.details')}
                            </h2>
                            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('pages.expenses.date')}
                                    </dt>
                                    <dd>{expense.expense_date ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('pages.expenses.created_by')}
                                    </dt>
                                    <dd>
                                        {expense.created_by_name ?? '—'} ·{' '}
                                        {formatWhen(expense.created_at)}
                                    </dd>
                                </div>
                            </dl>
                            {expense.description ? (
                                <p className="rounded-md border border-border/80 px-3 py-2 text-sm">
                                    {expense.description}
                                </p>
                            ) : null}
                        </section>

                        <section className="space-y-3">
                            <h2 className="text-sm font-medium">
                                {t('pages.expenses.receipt')}
                            </h2>
                            {expense.receipt ? (
                                <div className="flex flex-wrap items-center gap-3 rounded-md border border-border/80 px-3 py-3 text-sm">
                                    <Badge variant="secondary">
                                        {expense.receipt.mime_type}
                                    </Badge>
                                    <span className="min-w-0 flex-1 truncate">
                                        {expense.receipt.original_name}
                                    </span>
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={expense.receipt.download_url}>
                                            {t('pages.expenses.download')}
                                        </a>
                                    </Button>
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {t('pages.expenses.no_receipt')}
                                </p>
                            )}
                        </section>
                    </div>

                    <section className="space-y-3">
                        <h2 className="text-sm font-medium">
                            {t('pages.expenses.activity')}
                        </h2>
                        {activity.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('pages.expenses.no_activity')}
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {activity.map((row) => (
                                    <li
                                        key={row.id}
                                        className="rounded-md border border-border/80 px-3 py-2 text-sm"
                                    >
                                        <p className="font-medium">
                                            {row.action}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {row.user_name ?? '—'} ·{' '}
                                            {formatWhen(row.created_at)}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

ExpensesShow.layout = {
    breadcrumbs: [
        {
            title: 'Expenses',
            href: expensesIndex(),
        },
        {
            title: 'Details',
            href: '#',
        },
    ],
};
