import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Minus, Plus, Search, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import { formatMoney, fromMinor, toMinor } from '@/lib/money';
import { store as storeCustomer } from '@/routes/customers';
import {
    index as salesIndex,
    pos as posRoute,
    store as storeSale,
} from '@/routes/sales';
import { products as searchProducts } from '@/routes/sales/pos';
import { branch as switchBranch } from '@/routes/workspace';

type BranchOption = { id: number; name: string };
type CustomerOption = { id: number; name: string; phone?: string | null };
type PaymentOption = { value: string; label: string };

type PosProduct = {
    id: number;
    name: string;
    sku: string;
    barcode: string | null;
    quantity: number;
    selling_price_minor: number;
    selling_price_formatted: string;
};

type CartLine = {
    product_id: number;
    name: string;
    sku: string;
    quantity: number;
    max_quantity: number;
    unit_price_minor: number;
};

function newRequestId(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export default function SalesPos({
    branches,
    activeBranchId,
    products: initialProducts,
    customers: initialCustomers,
    paymentMethods,
    currency,
    permissions,
}: {
    branches: BranchOption[];
    activeBranchId: number;
    products: PosProduct[];
    customers: CustomerOption[];
    paymentMethods: PaymentOption[];
    currency: string;
    permissions: { discount: boolean; create_customer: boolean };
}) {
    const { t, locale } = useTranslations();
    const flash = usePage().props.flash as
        | { success?: string | null; error?: string | null }
        | undefined;
    const [branchId, setBranchId] = useState(String(activeBranchId));
    const [products, setProducts] = useState(initialProducts);
    const [customers, setCustomers] = useState(initialCustomers);
    const [search, setSearch] = useState('');
    const [searching, setSearching] = useState(false);
    const [cart, setCart] = useState<CartLine[]>([]);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [customerMode, setCustomerMode] = useState<'walkin' | 'saved'>(
        'walkin',
    );
    const [newCustomerName, setNewCustomerName] = useState('');
    const [creatingCustomer, setCreatingCustomer] = useState(false);

    const form = useForm({
        branch_id: Number(branchId),
        customer_id: '' as string | number,
        customer_name: '',
        payment_method: paymentMethods[0]?.value ?? 'cash',
        payment_reference: '',
        discount_amount: '',
        notes: '',
        client_request_id: newRequestId(),
        items: [] as Array<{ product_id: number; quantity: number }>,
    });

    useEffect(() => {
        setProducts(initialProducts);
        setBranchId(String(activeBranchId));
        setCart([]);
        form.setData('client_request_id', newRequestId());
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset cart when server branch stock reloads
    }, [activeBranchId, initialProducts]);

    useEffect(() => {
        const timer = window.setTimeout(async () => {
            if (search.trim() === '') {
                setProducts(initialProducts);
                return;
            }

            setSearching(true);

            try {
                const response = await fetch(
                    searchProducts.url({
                        query: {
                            search: search.trim(),
                            branch_id: Number(branchId),
                        },
                    }),
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    },
                );

                if (!response.ok) {
                    return;
                }

                const payload = (await response.json()) as {
                    products: PosProduct[];
                };
                setProducts(payload.products);
            } finally {
                setSearching(false);
            }
        }, 250);

        return () => window.clearTimeout(timer);
    }, [search, branchId, initialProducts]);

    const subtotalMinor = useMemo(
        () =>
            cart.reduce(
                (sum, line) => sum + line.unit_price_minor * line.quantity,
                0,
            ),
        [cart],
    );

    const discountMinor = Math.min(
        Math.max(0, toMinor(form.data.discount_amount || '0', currency)),
        subtotalMinor,
    );
    const totalMinor = subtotalMinor - discountMinor;

    const changeBranch = (nextBranchId: string) => {
        setBranchId(nextBranchId);
        router.post(
            switchBranch.url(),
            { branch_id: Number(nextBranchId) },
            {
                preserveScroll: true,
                onSuccess: () => router.visit(posRoute.url()),
            },
        );
    };

    const addProduct = (product: PosProduct) => {
        if (product.quantity < 1) {
            return;
        }

        setCart((current) => {
            const existing = current.find(
                (line) => line.product_id === product.id,
            );

            if (existing) {
                if (existing.quantity >= existing.max_quantity) {
                    return current;
                }

                return current.map((line) =>
                    line.product_id === product.id
                        ? { ...line, quantity: line.quantity + 1 }
                        : line,
                );
            }

            return [
                ...current,
                {
                    product_id: product.id,
                    name: product.name,
                    sku: product.sku,
                    quantity: 1,
                    max_quantity: product.quantity,
                    unit_price_minor: product.selling_price_minor,
                },
            ];
        });
    };

    const updateQuantity = (productId: number, quantity: number) => {
        setCart((current) =>
            current
                .map((line) => {
                    if (line.product_id !== productId) {
                        return line;
                    }

                    const next = Math.min(
                        line.max_quantity,
                        Math.max(0, quantity),
                    );

                    return { ...line, quantity: next };
                })
                .filter((line) => line.quantity > 0),
        );
    };

    const createQuickCustomer = async () => {
        if (!permissions.create_customer || newCustomerName.trim() === '') {
            return;
        }

        setCreatingCustomer(true);

        try {
            const token = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

            const response = await fetch(storeCustomer.url(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ name: newCustomerName.trim() }),
            });

            if (!response.ok) {
                return;
            }

            const payload = (await response.json()) as {
                customer: CustomerOption;
            };
            setCustomers((current) => [payload.customer, ...current]);
            setCustomerMode('saved');
            form.setData('customer_id', payload.customer.id);
            form.setData('customer_name', '');
            setNewCustomerName('');
        } finally {
            setCreatingCustomer(false);
        }
    };

    const submitSale = () => {
        if (cart.length === 0 || form.processing) {
            return;
        }

        form.transform((data) => ({
            ...data,
            branch_id: Number(branchId),
            customer_id:
                customerMode === 'saved' && data.customer_id !== ''
                    ? Number(data.customer_id)
                    : null,
            customer_name:
                customerMode === 'walkin' ? data.customer_name || null : null,
            discount_amount: permissions.discount
                ? data.discount_amount || '0'
                : '0',
            payment_reference: data.payment_reference || null,
            notes: data.notes || null,
            items: cart.map((line) => ({
                product_id: line.product_id,
                quantity: line.quantity,
            })),
        }));

        form.post(storeSale.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmOpen(false);
                setCart([]);
                form.setData('client_request_id', newRequestId());
                form.setData('discount_amount', '');
                form.setData('notes', '');
                form.setData('payment_reference', '');
            },
            onError: () => setConfirmOpen(false),
        });
    };

    return (
        <>
            <Head title={t('pages.pos.title', 'Point of sale')} />
            <div className="flex h-full flex-1 flex-col gap-4 p-3 md:p-4 lg:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            {t('pages.pos.title', 'Point of sale')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'pages.pos.description',
                                'Search stock, build a cart, and complete payment quickly.',
                            )}
                        </p>
                    </div>
                    <Button variant="outline" asChild className="w-full sm:w-auto">
                        <Link href={salesIndex()}>
                            {t('pages.pos.history', 'Sales history')}
                        </Link>
                    </Button>
                </div>

                {flash?.success ? (
                    <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200">
                        {flash.success}
                    </div>
                ) : null}

                {Object.keys(form.errors).length > 0 ? (
                    <div className="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        {Object.values(form.errors)[0]}
                    </div>
                ) : null}

                <div className="grid flex-1 gap-4 xl:grid-cols-[1.4fr_1fr]">
                    <section className="flex min-h-[28rem] flex-col gap-3 rounded-2xl border border-border/80 bg-card/70 p-3 md:p-4">
                        <div className="grid gap-3 sm:grid-cols-[12rem_1fr]">
                            <div className="space-y-1.5">
                                <Label htmlFor="pos_branch">
                                    {t('pages.pos.branch', 'Branch')}
                                </Label>
                                <select
                                    id="pos_branch"
                                    value={branchId}
                                    onChange={(event) =>
                                        changeBranch(event.target.value)
                                    }
                                    className="h-12 w-full rounded-xl border border-input bg-background px-3 text-base"
                                >
                                    {branches.map((branch) => (
                                        <option
                                            key={branch.id}
                                            value={branch.id}
                                        >
                                            {branch.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="pos_search">
                                    {t(
                                        'pages.pos.search',
                                        'Search name, SKU, or barcode',
                                    )}
                                </Label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        id="pos_search"
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder={t(
                                            'pages.pos.search_placeholder',
                                            'Type to find products…',
                                        )}
                                        className="h-12 rounded-xl pl-10 text-base"
                                        autoFocus
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="min-h-0 flex-1 overflow-auto">
                            {searching ? (
                                <p className="p-6 text-center text-sm text-muted-foreground">
                                    {t('pages.pos.searching', 'Searching…')}
                                </p>
                            ) : products.length === 0 ? (
                                <p className="p-6 text-center text-sm text-muted-foreground">
                                    {t(
                                        'pages.pos.no_products',
                                        'No products with available stock match this search.',
                                    )}
                                </p>
                            ) : (
                                <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                    {products.map((product) => (
                                        <button
                                            key={product.id}
                                            type="button"
                                            onClick={() => addProduct(product)}
                                            disabled={product.quantity < 1}
                                            className="rounded-2xl border border-border/80 bg-background p-4 text-left transition hover:border-primary/50 hover:bg-accent/40 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <div className="font-medium">
                                                        {product.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {product.sku}
                                                        {product.barcode
                                                            ? ` · ${product.barcode}`
                                                            : ''}
                                                    </div>
                                                </div>
                                                <Badge variant="secondary">
                                                    {product.quantity}
                                                </Badge>
                                            </div>
                                            <div className="mt-3 text-lg font-semibold">
                                                {product.selling_price_formatted}
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="flex min-h-[28rem] flex-col gap-4 rounded-2xl border border-border/80 bg-card/70 p-3 md:p-4">
                        <div>
                            <h2 className="font-display text-lg font-semibold">
                                {t('pages.pos.cart', 'Cart')}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'pages.pos.cart_hint',
                                    'Quantities cannot exceed available stock.',
                                )}
                            </p>
                        </div>

                        <div className="min-h-0 flex-1 space-y-2 overflow-auto">
                            {cart.length === 0 ? (
                                <p className="rounded-xl border border-dashed border-border px-4 py-10 text-center text-sm text-muted-foreground">
                                    {t(
                                        'pages.pos.empty_cart',
                                        'Tap a product to add it to the sale.',
                                    )}
                                </p>
                            ) : (
                                cart.map((line) => (
                                    <div
                                        key={line.product_id}
                                        className="rounded-xl border border-border/70 bg-background p-3"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <div className="font-medium">
                                                    {line.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {line.sku} · max{' '}
                                                    {line.max_quantity}
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                className="rounded-lg p-2 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                onClick={() =>
                                                    updateQuantity(
                                                        line.product_id,
                                                        0,
                                                    )
                                                }
                                                aria-label="Remove"
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>
                                        <div className="mt-3 flex items-center justify-between gap-3">
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    className="size-11 rounded-xl"
                                                    onClick={() =>
                                                        updateQuantity(
                                                            line.product_id,
                                                            line.quantity - 1,
                                                        )
                                                    }
                                                >
                                                    <Minus className="size-4" />
                                                </Button>
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    max={line.max_quantity}
                                                    value={line.quantity}
                                                    onChange={(event) =>
                                                        updateQuantity(
                                                            line.product_id,
                                                            Number(
                                                                event.target
                                                                    .value || 0,
                                                            ),
                                                        )
                                                    }
                                                    className="h-11 w-16 rounded-xl text-center text-base"
                                                />
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    className="size-11 rounded-xl"
                                                    disabled={
                                                        line.quantity >=
                                                        line.max_quantity
                                                    }
                                                    onClick={() =>
                                                        updateQuantity(
                                                            line.product_id,
                                                            line.quantity + 1,
                                                        )
                                                    }
                                                >
                                                    <Plus className="size-4" />
                                                </Button>
                                            </div>
                                            <div className="text-right font-semibold">
                                                {formatMoney(
                                                    line.unit_price_minor *
                                                        line.quantity,
                                                    currency,
                                                    locale,
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>

                        <div className="space-y-3 border-t border-border/70 pt-3">
                            <div className="grid gap-2">
                                <Label>
                                    {t('pages.pos.customer', 'Customer')}
                                </Label>
                                <div className="grid grid-cols-2 gap-2">
                                    <Button
                                        type="button"
                                        variant={
                                            customerMode === 'walkin'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        className="h-11 rounded-xl"
                                        onClick={() => {
                                            setCustomerMode('walkin');
                                            form.setData('customer_id', '');
                                        }}
                                    >
                                        {t('pages.pos.walk_in', 'Walk-in')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant={
                                            customerMode === 'saved'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        className="h-11 rounded-xl"
                                        onClick={() =>
                                            setCustomerMode('saved')
                                        }
                                    >
                                        {t('pages.pos.saved', 'Saved')}
                                    </Button>
                                </div>
                                {customerMode === 'walkin' ? (
                                    <Input
                                        value={form.data.customer_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'customer_name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder={t(
                                            'pages.pos.walk_in_name',
                                            'Optional walk-in name',
                                        )}
                                        className="h-11 rounded-xl"
                                    />
                                ) : (
                                    <select
                                        value={String(form.data.customer_id)}
                                        onChange={(event) =>
                                            form.setData(
                                                'customer_id',
                                                event.target.value,
                                            )
                                        }
                                        className="h-11 w-full rounded-xl border border-input bg-background px-3 text-base"
                                    >
                                        <option value="">
                                            {t(
                                                'pages.pos.select_customer',
                                                'Select customer',
                                            )}
                                        </option>
                                        {customers.map((customer) => (
                                            <option
                                                key={customer.id}
                                                value={customer.id}
                                            >
                                                {customer.name}
                                                {customer.phone
                                                    ? ` · ${customer.phone}`
                                                    : ''}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                {permissions.create_customer ? (
                                    <div className="flex gap-2">
                                        <Input
                                            value={newCustomerName}
                                            onChange={(event) =>
                                                setNewCustomerName(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder={t(
                                                'pages.pos.new_customer',
                                                'Quick-add customer name',
                                            )}
                                            className="h-11 rounded-xl"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="h-11 rounded-xl"
                                            disabled={creatingCustomer}
                                            onClick={createQuickCustomer}
                                        >
                                            {t('pages.pos.add', 'Add')}
                                        </Button>
                                    </div>
                                ) : null}
                            </div>

                            <div className="grid gap-2 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="payment_method">
                                        {t('pages.pos.payment', 'Payment')}
                                    </Label>
                                    <select
                                        id="payment_method"
                                        value={form.data.payment_method}
                                        onChange={(event) =>
                                            form.setData(
                                                'payment_method',
                                                event.target.value,
                                            )
                                        }
                                        className="h-11 w-full rounded-xl border border-input bg-background px-3 text-base"
                                    >
                                        {paymentMethods.map((method) => (
                                            <option
                                                key={method.value}
                                                value={method.value}
                                            >
                                                {method.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="payment_reference">
                                        {t(
                                            'pages.pos.reference',
                                            'Reference',
                                        )}
                                    </Label>
                                    <Input
                                        id="payment_reference"
                                        value={form.data.payment_reference}
                                        onChange={(event) =>
                                            form.setData(
                                                'payment_reference',
                                                event.target.value,
                                            )
                                        }
                                        className="h-11 rounded-xl"
                                    />
                                </div>
                            </div>

                            {permissions.discount ? (
                                <div className="space-y-1.5">
                                    <Label htmlFor="discount_amount">
                                        {t(
                                            'pages.pos.discount',
                                            'Discount',
                                        )}
                                    </Label>
                                    <Input
                                        id="discount_amount"
                                        value={form.data.discount_amount}
                                        onChange={(event) =>
                                            form.setData(
                                                'discount_amount',
                                                event.target.value,
                                            )
                                        }
                                        placeholder={fromMinor(0, currency)}
                                        className="h-11 rounded-xl"
                                    />
                                    <InputError
                                        message={form.errors.discount_amount}
                                    />
                                </div>
                            ) : null}

                            <div className="space-y-1 rounded-xl bg-muted/50 p-3 text-sm">
                                <div className="flex justify-between">
                                    <span>
                                        {t('pages.pos.subtotal', 'Subtotal')}
                                    </span>
                                    <span>
                                        {formatMoney(
                                            subtotalMinor,
                                            currency,
                                            locale,
                                        )}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span>
                                        {t('pages.pos.discount', 'Discount')}
                                    </span>
                                    <span>
                                        -
                                        {formatMoney(
                                            discountMinor,
                                            currency,
                                            locale,
                                        )}
                                    </span>
                                </div>
                                <div className="flex justify-between text-lg font-semibold">
                                    <span>
                                        {t('pages.pos.total', 'Total')}
                                    </span>
                                    <span>
                                        {formatMoney(
                                            totalMinor,
                                            currency,
                                            locale,
                                        )}
                                    </span>
                                </div>
                            </div>

                            <Button
                                type="button"
                                className="h-14 w-full rounded-2xl text-base"
                                disabled={cart.length === 0 || form.processing}
                                onClick={() => setConfirmOpen(true)}
                            >
                                {form.processing
                                    ? t('pages.pos.processing', 'Processing…')
                                    : t(
                                          'pages.pos.complete',
                                          'Complete sale',
                                      )}
                            </Button>
                        </div>
                    </section>
                </div>
            </div>

            {confirmOpen ? (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center">
                    <div className="w-full max-w-md rounded-2xl bg-background p-5 shadow-xl">
                        <div className="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h3 className="font-display text-xl font-semibold">
                                    {t(
                                        'pages.pos.confirm_title',
                                        'Confirm sale',
                                    )}
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'pages.pos.confirm_description',
                                        'This will deduct stock and record the payment.',
                                    )}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setConfirmOpen(false)}
                                className="rounded-lg p-2 hover:bg-muted"
                                aria-label="Close"
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                        <div className="mb-5 space-y-2 rounded-xl bg-muted/60 p-4 text-sm">
                            <div className="flex justify-between">
                                <span>Items</span>
                                <span>{cart.length}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Payment</span>
                                <span>
                                    {paymentMethods.find(
                                        (method) =>
                                            method.value ===
                                            form.data.payment_method,
                                    )?.label ?? form.data.payment_method}
                                </span>
                            </div>
                            <div className="flex justify-between text-base font-semibold">
                                <span>Total</span>
                                <span>
                                    {formatMoney(
                                        totalMinor,
                                        currency,
                                        locale,
                                    )}
                                </span>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="h-12 rounded-xl"
                                onClick={() => setConfirmOpen(false)}
                                disabled={form.processing}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                className="h-12 rounded-xl"
                                onClick={submitSale}
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? 'Saving…'
                                    : 'Confirm & charge'}
                            </Button>
                        </div>
                    </div>
                </div>
            ) : null}
        </>
    );
}

SalesPos.layout = {
    breadcrumbs: [
        {
            title: 'Sales',
            href: '/sales',
        },
        {
            title: 'POS',
            href: '/sales/pos',
        },
    ],
};
