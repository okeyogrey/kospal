import { Head, Link, router } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import {
    index as salesIndex,
    pos,
    show as showSale,
} from '@/routes/sales';

type SaleRow = {
    id: number;
    sale_number: string;
    status: string;
    payment_method_label: string;
    branch_name: string | null;
    cashier_name: string | null;
    customer_name: string | null;
    items_count: number;
    total_formatted: string;
    created_at: string | null;
};

type Option = { id: number; name: string };
type PaymentOption = { value: string; label: string };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

export default function SalesIndex({
    sales,
    branches,
    cashiers,
    customers,
    paymentMethods,
    statuses,
    filters,
    permissions,
}: {
    sales: Paginated<SaleRow>;
    branches: Option[];
    cashiers: Option[];
    customers: Option[];
    paymentMethods: PaymentOption[];
    statuses: string[];
    filters: {
        search: string;
        date_from: string | null;
        date_to: string | null;
        branch_id: number | null;
        cashier_id: number | null;
        customer_id: number | null;
        payment_method: string | null;
        status: string | null;
    };
    permissions: {
        create: boolean;
        void: boolean;
        discount: boolean;
    };
}) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('pages.sales.title', 'Sales')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            {t('pages.sales.title', 'Sales')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'pages.sales.description',
                                'Record and review point-of-sale transactions.',
                            )}
                        </p>
                    </div>
                    {permissions.create ? (
                        <Button asChild className="w-full sm:w-auto">
                            <Link href={pos()}>
                                {t('pages.sales.open_pos', 'Open POS')}
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <form
                    className="grid gap-2 md:grid-cols-2 xl:grid-cols-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.get(
                            salesIndex.url({
                                query: {
                                    search:
                                        String(data.get('search') ?? '') ||
                                        undefined,
                                    date_from:
                                        String(data.get('date_from') ?? '') ||
                                        undefined,
                                    date_to:
                                        String(data.get('date_to') ?? '') ||
                                        undefined,
                                    branch_id:
                                        String(data.get('branch_id') ?? '') ||
                                        undefined,
                                    cashier_id:
                                        String(data.get('cashier_id') ?? '') ||
                                        undefined,
                                    customer_id:
                                        String(data.get('customer_id') ?? '') ||
                                        undefined,
                                    payment_method:
                                        String(
                                            data.get('payment_method') ?? '',
                                        ) || undefined,
                                    status:
                                        String(data.get('status') ?? '') ||
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
                        placeholder="Search sale # or customer"
                        defaultValue={filters.search}
                    />
                    <Input
                        type="date"
                        name="date_from"
                        defaultValue={filters.date_from ?? ''}
                    />
                    <Input
                        type="date"
                        name="date_to"
                        defaultValue={filters.date_to ?? ''}
                    />
                    <select
                        name="branch_id"
                        defaultValue={filters.branch_id ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All branches</option>
                        {branches.map((branch) => (
                            <option key={branch.id} value={branch.id}>
                                {branch.name}
                            </option>
                        ))}
                    </select>
                    <select
                        name="cashier_id"
                        defaultValue={filters.cashier_id ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All cashiers</option>
                        {cashiers.map((cashier) => (
                            <option key={cashier.id} value={cashier.id}>
                                {cashier.name}
                            </option>
                        ))}
                    </select>
                    <select
                        name="customer_id"
                        defaultValue={filters.customer_id ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All customers</option>
                        {customers.map((customer) => (
                            <option key={customer.id} value={customer.id}>
                                {customer.name}
                            </option>
                        ))}
                    </select>
                    <select
                        name="payment_method"
                        defaultValue={filters.payment_method ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All payments</option>
                        {paymentMethods.map((method) => (
                            <option key={method.value} value={method.value}>
                                {method.label}
                            </option>
                        ))}
                    </select>
                    <select
                        name="status"
                        defaultValue={filters.status ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All statuses</option>
                        {statuses.map((status) => (
                            <option key={status} value={status}>
                                {status}
                            </option>
                        ))}
                    </select>
                    <Button type="submit" variant="outline">
                        Filter
                    </Button>
                </form>

                {sales.data.length === 0 ? (
                    <EmptyState
                        icon={<ShoppingCart className="size-5" />}
                        title={t('pages.sales.empty_title', 'No sales yet')}
                        description={t(
                            'pages.sales.empty_description',
                            'Completed POS sales will appear here.',
                        )}
                        action={
                            permissions.create ? (
                                <Button asChild>
                                    <Link href={pos()}>Open POS</Link>
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-2xl border border-border/80">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[56rem] text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Sale #
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Branch
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Cashier
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Customer
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Payment
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Total
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            When
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sales.data.map((sale) => (
                                        <tr
                                            key={sale.id}
                                            className="border-t border-border/70"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={showSale(sale.id)}
                                                    className="font-medium text-primary hover:underline"
                                                >
                                                    {sale.sale_number}
                                                </Link>
                                                <div className="text-xs text-muted-foreground">
                                                    {sale.items_count} items
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {sale.branch_name}
                                            </td>
                                            <td className="px-4 py-3">
                                                {sale.cashier_name}
                                            </td>
                                            <td className="px-4 py-3">
                                                {sale.customer_name ??
                                                    'Walk-in'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {sale.payment_method_label}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        sale.status ===
                                                        'voided'
                                                            ? 'destructive'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {sale.status}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 font-medium">
                                                {sale.total_formatted}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {sale.created_at
                                                    ? new Date(
                                                          sale.created_at,
                                                      ).toLocaleString()
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination links={sales.links} />
                    </div>
                )}
            </div>
        </>
    );
}

SalesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Sales',
            href: salesIndex(),
        },
    ],
};
