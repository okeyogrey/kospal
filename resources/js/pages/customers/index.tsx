import { Form, Head, router, useForm } from '@inertiajs/react';
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
};

type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
};

export default function CustomersIndex({
    customers,
    filters,
    permissions,
}: {
    customers: Paginated<CustomerRow>;
    filters: { search: string; status: string | null };
    permissions: { create: boolean; manage: boolean };
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
    });
    const editForm = useForm({
        name: '',
        phone: '',
        email: '',
        address: '',
        notes: '',
        is_active: true,
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
                                'Save returning customer contacts for faster checkout.',
                            )}
                        </p>
                    </div>
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
                        </select>
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>
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
                                    <table className="w-full min-w-[40rem] text-sm">
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
                                                        {customer.name}
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
                                                            {permissions.manage ? (
                                                                <>
                                                                    <Button
                                                                        type="button"
                                                                        size="sm"
                                                                        variant="outline"
                                                                        onClick={() =>
                                                                            startEdit(
                                                                                customer,
                                                                            )
                                                                        }
                                                                    >
                                                                        Edit
                                                                    </Button>
                                                                    <Form
                                                                        {...destroy.form(
                                                                            customer.id,
                                                                        )}
                                                                        options={{
                                                                            preserveScroll: true,
                                                                        }}
                                                                        onSubmit={(
                                                                            event,
                                                                        ) => {
                                                                            if (
                                                                                !confirm(
                                                                                    'Delete this customer?',
                                                                                )
                                                                            ) {
                                                                                event.preventDefault();
                                                                            }
                                                                        }}
                                                                    >
                                                                        <Button
                                                                            type="submit"
                                                                            size="sm"
                                                                            variant="ghost"
                                                                        >
                                                                            Delete
                                                                        </Button>
                                                                    </Form>
                                                                </>
                                                            ) : (
                                                                <span className="text-xs text-muted-foreground">
                                                                    View only
                                                                </span>
                                                            )}
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
                                <div className="space-y-2">
                                    <Label htmlFor="address">Address</Label>
                                    <Input
                                        id="address"
                                        value={createForm.data.address}
                                        onChange={(event) =>
                                            createForm.setData(
                                                'address',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
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
                                <div className="space-y-2">
                                    <Label htmlFor="edit_phone">Phone</Label>
                                    <Input
                                        id="edit_phone"
                                        value={editForm.data.phone}
                                        onChange={(event) =>
                                            editForm.setData(
                                                'phone',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="edit_email">Email</Label>
                                    <Input
                                        id="edit_email"
                                        value={editForm.data.email}
                                        onChange={(event) =>
                                            editForm.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
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
