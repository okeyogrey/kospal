import { Head, Link, router } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/money';

type InvoiceRow = {
    id: number;
    reference: string | null;
    supplier_invoice_number: string | null;
    status: string;
    supplier_name: string | null;
    total: number;
    total_formatted: string;
    amount_paid: number;
    amount_due: number;
    created_at: string | null;
};

type Option = { id: number; name: string };

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

const statusVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    draft: 'secondary',
    posted: 'default',
    partially_paid: 'outline',
    paid: 'outline',
    void: 'destructive',
};

export default function SupplierInvoicesIndex({
    invoices,
    suppliers,
    statuses,
    filters,
    currency,
}: {
    invoices: Paginated<InvoiceRow>;
    suppliers: Option[];
    statuses: string[];
    filters: {
        search: string;
        status: string | null;
        supplier_id: number | null;
    };
    currency: string;
}) {
    return (
        <>
            <Head title="Supplier invoices" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Supplier invoices
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Track amounts owed to suppliers and payment
                            status.
                        </p>
                    </div>
                    <Button asChild className="w-full sm:w-auto">
                        <Link href="/supplier-invoices/create">
                            Create invoice
                        </Link>
                    </Button>
                </div>

                <form
                    className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.get(
                            '/supplier-invoices',
                            {
                                search: String(data.get('search') ?? '') || undefined,
                                status: String(data.get('status') ?? '') || undefined,
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
                        placeholder="Search reference / invoice #…"
                        defaultValue={filters.search}
                    />
                    <select
                        name="status"
                        defaultValue={filters.status ?? ''}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All statuses</option>
                        {statuses.map((status) => (
                            <option key={status} value={status}>
                                {status.replaceAll('_', ' ')}
                            </option>
                        ))}
                    </select>
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

                {invoices.data.length === 0 ? (
                    <EmptyState
                        icon={<Receipt className="size-5" />}
                        title="No supplier invoices yet"
                        description="Create an invoice or post a goods received note to generate one."
                    />
                ) : (
                    <div className="space-y-3">
                        {invoices.data.map((invoice) => (
                            <Link
                                key={invoice.id}
                                href={`/supplier-invoices/${invoice.id}`}
                                className="block rounded-lg border border-border/80 px-4 py-3 transition hover:bg-muted/40"
                            >
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {invoice.reference ??
                                                    `#${invoice.id}`}
                                            </span>
                                            <Badge
                                                variant={
                                                    statusVariant[
                                                        invoice.status
                                                    ] ?? 'secondary'
                                                }
                                            >
                                                {invoice.status.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </Badge>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {invoice.supplier_name}
                                            {invoice.supplier_invoice_number
                                                ? ` · Inv# ${invoice.supplier_invoice_number}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="text-right text-sm">
                                        <p className="font-medium tabular-nums">
                                            {invoice.total_formatted}
                                        </p>
                                        {invoice.amount_due > 0 ? (
                                            <p className="text-xs text-muted-foreground tabular-nums">
                                                Due{' '}
                                                {formatMoney(
                                                    invoice.amount_due,
                                                    currency,
                                                )}
                                            </p>
                                        ) : (
                                            <p className="text-xs text-muted-foreground">
                                                Fully paid
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </Link>
                        ))}
                        <Pagination links={invoices.links} />
                    </div>
                )}
            </div>
        </>
    );
}
