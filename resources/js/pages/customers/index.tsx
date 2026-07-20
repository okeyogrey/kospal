import { Head, Link, router, useForm } from '@inertiajs/react';
import { ContactRound } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Pagination, type PaginationLink } from '@/components/pagination';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import {
    destroy,
    index as customersIndex,
    show as customersShow,
    store,
    update,
} from '@/routes/customers';

type CustomerRow = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    notes: string | null;
    is_active: boolean;
    sales_count: number;
    credit_enabled?: boolean;
    payment_terms_days?: number | null;
    outstanding_balance?: number;
    outstanding_balance_formatted?: string;
    credit_limit?: number | null;
    credit_limit_formatted?: string | null;
};

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

export default function CustomersIndex({
    customers,
    filters,
    permissions,
    hasCreditFeature,
}: {
    customers: Paginated<CustomerRow>;
    filters: { search: string; status: string | null };
    permissions: {
        create: boolean;
        manage: boolean;
        recordPayments: boolean;
    };
    hasCreditFeature: boolean;
    currency: string;
}) {
    const { t } = useTranslations();
    const [editingId, setEditingId] = useState<number | null>(null);
    const createForm = useForm({
        name: '',
        phone: '',
        email: '',
        address: '',
        notes: '',
        is_active: true,
        credit_enabled: false,
        credit_limit: '',
        payment_terms_days: '',
    });
    const editForm = useForm({
        name: '',
        phone: '',
        email: '',
        address: '',
        notes: '',
        is_active: true,
        credit_enabled: false,
        credit_limit: '',
        payment_terms_days: '',
    });

    const startEdit = (customer: CustomerRow) => {
        setEditingId(customer.id);
        editForm.setData({
            name: customer.name,
            phone: customer.phone ?? '',
            email: customer.email ?? '',
            address: customer.address ?? '',
            notes: customer.notes ?? '',
            is_active: customer.is_active,
            credit_enabled: customer.credit_enabled ?? false,
            credit_limit:
                customer.credit_limit != null
                    ? String(customer.credit_limit)
                    : '',
            payment_terms_days:
                customer.payment_terms_days != null
                    ? String(customer.payment_terms_days)
                    : '',
        });
        editForm.clearErrors();
    };

    return (
        <>
            <Head title={t('pages.customers.title', 'Customers')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            {t('pages.customers.title', 'Customers')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'pages.customers.description',
                                'Manage customer contacts, credit limits, and account balances.',
                            )}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {permissions.recordPayments && hasCreditFeature ? (
                            <Button variant="outline" asChild>
                                <Link href="/customer-payments/create">
                                    Record payment
                                </Link>
                            </Button>
                        ) : null}
                        <form
                            className="flex gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                const data = new FormData(event.currentTarget);
                                router.get(
                                    customersIndex.url({
                                        query: {
                                            search: String(
                                                data.get('search') ?? '',
                                            ),
                                            status:
                                                String(
                                                    data.get('status') ?? '',
                                                ) || undefined,
                                        },
                                    }),
                                    {},
                                    { preserveState: true },
                                );
                            }}
                        >
                            <Input
                                name="search"
                                placeholder="Search customers"
                                defaultValue={filters.search}
                                className="w-48 sm:w-64"
                            />
                            <select
                                name="status"
                                defaultValue={filters.status ?? ''}
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                {hasCreditFeature ? (
                                    <option value="with_balance">
                                        With credit
                                    </option>
                                ) : null}
                            </select>
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                        </form>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
                    <div className="rounded-2xl border border-border/80 bg-card/80">
                        {customers.data.length === 0 ? (
                            <EmptyState
                                icon={<ContactRound className="size-5" />}
                                title={t(
                                    'pages.customers.empty_title',
                                    'No customers yet',
                                )}
                                description={t(
                                    'pages.customers.empty_description',
                                    'Add a customer to attach them to future sales.',
                                )}
                            />
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[48rem] text-sm">
                                        <thead className="bg-muted/40 text-left">
                                            <tr>
                                                <th className="px-4 py-3 font-medium">
                                                    Name
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Contact
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Sales
                                                </th>
                                                {hasCreditFeature ? (
                                                    <th className="px-4 py-3 font-medium">
                                                        Balance
                                                    </th>
                                                ) : null}
                                                <th className="px-4 py-3 font-medium">
                                                    Status
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {customers.data.map((customer) => (
                                                <tr
                                                    key={customer.id}
                                                    className="border-t border-border/70"
                                                >
                                                    <td className="px-4 py-3 font-medium">
                                                        <Link
                                                            href={customersShow.url(
                                                                customer.id,
                                                            )}
                                                            className="hover:underline"
                                                        >
                                                            {customer.name}
                                                        </Link>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div>
                                                            {customer.phone ??
                                                                '—'}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {customer.email ??
                                                                '—'}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {customer.sales_count}
                                                    </td>
                                                    {hasCreditFeature ? (
                                                        <td className="px-4 py-3">
                                                            {customer.credit_enabled ? (
                                                                <div>
                                                                    <div className="font-medium tabular-nums">
                                                                        {customer.outstanding_balance_formatted ??
                                                                            '—'}
                                                                    </div>
                                                                    {customer.credit_limit_formatted ? (
                                                                        <div className="text-xs text-muted-foreground">
                                                                            Limit{' '}
                                                                            {
                                                                                customer.credit_limit_formatted
                                                                            }
                                                                        </div>
                                                                    ) : null}
                                                                </div>
                                                            ) : (
                                                                <span className="text-muted-foreground">
                                                                    —
                                                                </span>
                                                            )}
                                                        </td>
                                                    ) : null}
                                                    <td className="px-4 py-3">
                                                        <Badge
                                                            variant={
                                                                customer.is_active
                                                                    ? 'secondary'
                                                                    : 'outline'
                                                            }
                                                        >
                                                            {customer.is_active
                                                                ? 'Active'
                                                                : 'Inactive'}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex gap-2">
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={customersShow.url(
                                                                        customer.id,
                                                                    )}
                                                                >
                                                                    View
                                                                </Link>
                                                            </Button>
                                                            {permissions.manage ? (
                                                                <>
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="ghost"
                                                                        onClick={() =>
                                                                            startEdit(
                                                                                customer,
                                                                            )
                                                                        }
                                                                    >
                                                                        Edit
                                                                    </Button>
                                                                    <form
                                                                        onSubmit={(
                                                                            event,
                                                                        ) => {
                                                                            event.preventDefault();
                                                                            if (
                                                                                !confirm(
                                                                                    'Delete this customer?',
                                                                                )
                                                                            ) {
                                                                                return;
                                                                            }
                                                                            router.delete(
                                                                                destroy.url(
                                                                                    customer.id,
                                                                                ),
                                                                                {
                                                                                    preserveScroll: true,
                                                                                },
                                                                            );
                                                                        }}
                                                                    >
                                                                        <Button
                                                                            type="submit"
                                                                            size="sm"
                                                                            variant="ghost"
                                                                        >
                                                                            Delete
                                                                        </Button>
                                                                    </form>
                                                                </>
                                                            ) : null}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <Pagination links={customers.links} />
                            </>
                        )}
                    </div>

                    <div className="space-y-4">
                        {permissions.create ? (
                            <form
                                className="space-y-3 rounded-2xl border border-border/80 p-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    createForm.transform((data) => ({
                                        ...data,
                                        credit_limit:
                                            data.credit_limit !== ''
                                                ? Number(data.credit_limit)
                                                : null,
                                        payment_terms_days:
                                            data.payment_terms_days !== ''
                                                ? Number(
                                                      data.payment_terms_days,
                                                  )
                                                : null,
                                    }));
                                    createForm.post(store.url(), {
                                        preserveScroll: true,
                                        onSuccess: () => createForm.reset(),
                                    });
                                }}
                            >
                                <h2 className="font-display text-lg font-semibold">
                                    Add customer
                                </h2>
                                <div className="space-y-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={createForm.data.name}
                                        onChange={(event) =>
                                            createForm.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={createForm.errors.name}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="phone">Phone</Label>
                                    <Input
                                        id="phone"
                                        value={createForm.data.phone}
                                        onChange={(event) =>
                                            createForm.setData(
                                                'phone',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        value={createForm.data.email}
                                        onChange={(event) =>
                                            createForm.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                {hasCreditFeature ? (
                                    <>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    createForm.data
                                                        .credit_enabled
                                                }
                                                onChange={(event) =>
                                                    createForm.setData(
                                                        'credit_enabled',
                                                        event.target.checked,
                                                    )
                                                }
                                            />
                                            Enable credit
                                        </label>
                                        {createForm.data.credit_enabled ? (
                                            <>
                                                <div className="space-y-2">
                                                    <Label htmlFor="credit_limit">
                                                        Credit limit (minor
                                                        units)
                                                    </Label>
                                                    <Input
                                                        id="credit_limit"
                                                        type="number"
                                                        min={0}
                                                        value={
                                                            createForm.data
                                                                .credit_limit
                                                        }
                                                        onChange={(event) =>
                                                            createForm.setData(
                                                                'credit_limit',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        placeholder="Leave empty for unlimited"
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor="payment_terms_days">
                                                        Payment terms (days)
                                                    </Label>
                                                    <Input
                                                        id="payment_terms_days"
                                                        type="number"
                                                        min={0}
                                                        value={
                                                            createForm.data
                                                                .payment_terms_days
                                                        }
                                                        onChange={(event) =>
                                                            createForm.setData(
                                                                'payment_terms_days',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </>
                                        ) : null}
                                    </>
                                ) : null}
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={createForm.processing}
                                >
                                    Create customer
                                </Button>
                            </form>
                        ) : null}

                        {editingId && permissions.manage ? (
                            <form
                                className="space-y-3 rounded-2xl border border-border/80 p-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    editForm.transform((data) => ({
                                        ...data,
                                        credit_limit:
                                            data.credit_limit !== ''
                                                ? Number(data.credit_limit)
                                                : null,
                                        payment_terms_days:
                                            data.payment_terms_days !== ''
                                                ? Number(
                                                      data.payment_terms_days,
                                                  )
                                                : null,
                                    }));
                                    editForm.patch(update.url(editingId), {
                                        preserveScroll: true,
                                        onSuccess: () => setEditingId(null),
                                    });
                                }}
                            >
                                <h2 className="font-display text-lg font-semibold">
                                    Edit customer
                                </h2>
                                <div className="space-y-2">
                                    <Label htmlFor="edit_name">Name</Label>
                                    <Input
                                        id="edit_name"
                                        value={editForm.data.name}
                                        onChange={(event) =>
                                            editForm.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={editForm.errors.name}
                                    />
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={editForm.data.is_active}
                                        onChange={(event) =>
                                            editForm.setData(
                                                'is_active',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Active
                                </label>
                                {hasCreditFeature ? (
                                    <>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    editForm.data.credit_enabled
                                                }
                                                onChange={(event) =>
                                                    editForm.setData(
                                                        'credit_enabled',
                                                        event.target.checked,
                                                    )
                                                }
                                            />
                                            Enable credit
                                        </label>
                                        {editForm.data.credit_enabled ? (
                                            <div className="space-y-2">
                                                <Label htmlFor="edit_credit_limit">
                                                    Credit limit (minor units)
                                                </Label>
                                                <Input
                                                    id="edit_credit_limit"
                                                    type="number"
                                                    min={0}
                                                    value={
                                                        editForm.data
                                                            .credit_limit
                                                    }
                                                    onChange={(event) =>
                                                        editForm.setData(
                                                            'credit_limit',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        ) : null}
                                    </>
                                ) : null}
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="flex-1"
                                        onClick={() => setEditingId(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        className="flex-1"
                                        disabled={editForm.processing}
                                    >
                                        Save
                                    </Button>
                                </div>
                            </form>
                        ) : null}
                    </div>
                </div>
            </div>
        </>
    );
}

CustomersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Customers',
            href: customersIndex(),
        },
    ],
};
