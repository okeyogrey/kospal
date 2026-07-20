import { Head, Link, router } from '@inertiajs/react';
import { Landmark } from 'lucide-react';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type PaymentRow = {
    id: number;
    reference: string | null;
    supplier_name: string | null;
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

export default function SupplierPaymentsIndex({
    payments,
    suppliers,
    filters,
}: {
    payments: Paginated<PaymentRow>;
    suppliers: Option[];
    filters: {
        search: string;
        supplier_id: number | null;
    };
    currency: string;
}) {
    return (
        <>
            <Head title="Supplier payments" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Supplier payments
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Record payments to suppliers and allocate them to
                            invoices.
                        </p>
                    </div>
                    <Button asChild className="w-full sm:w-auto">
                        <Link href="/supplier-payments/create">
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
                            '/supplier-payments',
                            {
                                search: String(data.get('search') ?? '') || undefined,
                                supplier_id:
                                    String(data.get('supplier_id') ?? '') ||
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
                        name="supplier_id"
                        defaultValue={filters.supplier_id ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">Any supplier</option>
                        {suppliers.map((supplier) => (
                            <option key={supplier.id} value={supplier.id}>
                                {supplier.name}
                            </option>
                        ))}
                    </select>
                    <Button type="submit" variant="outline">
                        Filter
                    </Button>
                </form>

                {payments.data.length === 0 ? (
                    <EmptyState
                        icon={<Landmark className="size-5" />}
                        title="No supplier payments yet"
                        description="Record a payment to settle supplier invoices."
                    />
                ) : (
                    <div className="space-y-3">
                        {payments.data.map((payment) => (
                            <Link
                                key={payment.id}
                                href={`/supplier-payments/${payment.id}`}
                                className="block rounded-lg border border-border/80 px-4 py-3 transition hover:bg-muted/40"
                            >
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {payment.reference ??
                                                    `#${payment.id}`}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {payment.method.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </span>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {payment.supplier_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {payment.allocations_count}{' '}
                                            invoice
                                            {payment.allocations_count === 1
                                                ? ''
                                                : 's'}
                                            {payment.created_by_name
                                                ? ` · ${payment.created_by_name}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="text-right text-sm">
                                        <p className="font-medium tabular-nums">
                                            {payment.amount_formatted}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {payment.paid_at ?? '—'}
                                        </p>
                                    </div>
                                </div>
                            </Link>
                        ))}
                        <Pagination links={payments.links} />
                    </div>
                )}
            </div>
        </>
    );
}
