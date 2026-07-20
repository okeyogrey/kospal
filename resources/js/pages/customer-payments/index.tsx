import { Head, Link, router } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type PaymentRow = {
    id: number;
    reference: string | null;
    customer_name: string | null;
    method: string;
    amount: number;
    amount_formatted: string;
    paid_at: string | null;
    allocations_count: number;
    created_by_name: string | null;
};

type Option = { id: number; name: string };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

export default function CustomerPaymentsIndex({
    payments,
    customers,
    filters,
}: {
    payments: Paginated<PaymentRow>;
    customers: Option[];
    filters: {
        search: string;
        customer_id: number | null;
    };
    currency: string;
}) {
    return (
        <>
            <Head title="Customer payments" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Customer payments
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Record payments from customers and apply them to
                            open credit sales.
                        </p>
                    </div>
                    <Button asChild className="w-full sm:w-auto">
                        <Link href="/customer-payments/create">
                            Record payment
                        </Link>
                    </Button>
                </div>

                <form
                    className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.get(
                            '/customer-payments',
                            {
                                search: String(data.get('search') ?? '') || undefined,
                                customer_id:
                                    String(data.get('customer_id') ?? '') ||
                                    undefined,
                            },
                            { preserveState: true },
                        );
                    }}
                >
                    <Input
                        name="search"
                        placeholder="Search reference…"
                        defaultValue={filters.search}
                    />
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
                    <Button type="submit" variant="outline">
                        Filter
                    </Button>
                </form>

                {payments.data.length === 0 ? (
                    <EmptyState
                        icon={<Wallet className="size-5" />}
                        title="No customer payments yet"
                        description="Record a payment when a customer settles their account."
                    />
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-2xl border border-border/80">
                            <table className="w-full min-w-[40rem] text-sm">
                                <thead className="bg-muted/40 text-left">
                                    <tr>
                                        <th className="px-4 py-3">Reference</th>
                                        <th className="px-4 py-3">Customer</th>
                                        <th className="px-4 py-3">Method</th>
                                        <th className="px-4 py-3 text-right">
                                            Amount
                                        </th>
                                        <th className="px-4 py-3">Paid on</th>
                                        <th className="px-4 py-3">Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {payments.data.map((payment) => (
                                        <tr
                                            key={payment.id}
                                            className="border-t border-border/70"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={`/customer-payments/${payment.id}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {payment.reference ??
                                                        `#${payment.id}`}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3">
                                                {payment.customer_name ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 capitalize">
                                                {payment.method.replace(
                                                    '_',
                                                    ' ',
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {payment.amount_formatted}
                                            </td>
                                            <td className="px-4 py-3">
                                                {payment.paid_at ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {payment.allocations_count}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination links={payments.links} />
                    </>
                )}
            </div>
        </>
    );
}
