import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Minus, Pause, Play, Plus, Search, Split, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import { formatMoney, fromMinor, toMinor } from '@/lib/money';
import { store as storeCustomer } from '@/routes/customers';
import {
    hold as holdSale,
    index as salesIndex,
    pos as posRoute,
    store as storeSale,
} from '@/routes/sales';
import { discard as discardHeld, resume as resumeHeld } from '@/routes/sales/held';
import { barcode as lookupBarcode, products as searchProducts } from '@/routes/sales/pos';
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
    cost_price_minor?: number;
    min_selling_price_minor: number;
    is_negotiable: boolean;
    selling_price_formatted: string;
    min_selling_price_formatted?: string;
};

type CartLine = {
    product_id: number;
    name: string;
    sku: string;
    quantity: number;
    max_quantity: number;
    unit_price_minor: number;
    list_unit_price_minor: number;
    min_selling_price_minor: number;
    is_negotiable: boolean;
};

type PaymentLine = {
    key: string;
    method: string;
    amount: string;
    reference: string;
};

type HeldSale = {
    id: number;
    sale_number: string;
    held_label: string | null;
    held_at: string | null;
    cashier_name: string | null;
    customer_id: number | null;
    customer_name: string | null;
    discount_amount_minor: number;
    notes: string | null;
    item_count: number;
    total_minor: number;
    total_formatted: string;
};

function newRequestId(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function csrfToken(): string | undefined {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? undefined
    );
}

export default function SalesPos({
    branches,
    activeBranchId,
    products: initialProducts,
    heldSales: initialHeldSales,
    customers: initialCustomers,
    paymentMethods,
    currency,
    permissions,
}: {
    branches: BranchOption[];
    activeBranchId: number;
    products: PosProduct[];
    heldSales: HeldSale[];
    customers: CustomerOption[];
    paymentMethods: PaymentOption[];
    currency: string;
    permissions: {
        discount: boolean;
        negotiate: boolean;
        approve_self: boolean;
        create_customer: boolean;
        hold: boolean;
        negotiation_floor_percent: number;
    };
}) {
    const { t, locale } = useTranslations();
    const flash = usePage().props.flash as
        | { success?: string | null; error?: string | null }
        | undefined;
    const scanRef = useRef<HTMLInputElement>(null);
    const [branchId, setBranchId] = useState(String(activeBranchId));
    const [products, setProducts] = useState(initialProducts);
    const [heldSales, setHeldSales] = useState(initialHeldSales);
    const [customers, setCustomers] = useState(initialCustomers);
    const [search, setSearch] = useState('');
    const [searching, setSearching] = useState(false);
    const [scanBusy, setScanBusy] = useState(false);
    const [scanMessage, setScanMessage] = useState<string | null>(null);
    const [cart, setCart] = useState<CartLine[]>([]);
    const [editingPriceId, setEditingPriceId] = useState<number | null>(null);
    const [priceDraft, setPriceDraft] = useState('');
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [approvalOpen, setApprovalOpen] = useState(false);
    const [pendingAction, setPendingAction] = useState<'sale' | 'hold'>('sale');
    const [heldOpen, setHeldOpen] = useState(false);
    const [splitMode, setSplitMode] = useState(false);
    const [heldSaleId, setHeldSaleId] = useState<number | null>(null);
    const [heldLabel, setHeldLabel] = useState('');
    const [cashTendered, setCashTendered] = useState('');
    const [managerLogin, setManagerLogin] = useState('');
    const [managerPassword, setManagerPassword] = useState('');
    const [managerPin, setManagerPin] = useState('');
    const [customerMode, setCustomerMode] = useState<'walkin' | 'saved'>(
        'walkin',
    );
    const [newCustomerName, setNewCustomerName] = useState('');
    const [creatingCustomer, setCreatingCustomer] = useState(false);
    const [payments, setPayments] = useState<PaymentLine[]>([
        {
            key: 'p1',
            method: paymentMethods[0]?.value ?? 'cash',
            amount: '',
            reference: '',
        },
    ]);

    const form = useForm({
        branch_id: Number(branchId),
        customer_id: '' as string | number,
        customer_name: '',
        payment_method: paymentMethods[0]?.value ?? 'cash',
        payment_reference: '',
        discount_amount: '',
        notes: '',
        client_request_id: newRequestId(),
        held_sale_id: null as number | null,
        cash_tendered: '',
        change_given: '',
        manager_approval: null as {
            pin: string;
            login: string;
            password: string;
        } | null,
        payments: [] as Array<{
            method: string;
            amount: string;
            reference: string | null;
            tendered_amount: string | null;
            change_amount: string | null;
        }>,
        items: [] as Array<{
            product_id: number;
            quantity: number;
            unit_price: string;
            list_unit_price: string;
        }>,
    });

    useEffect(() => {
        setProducts(initialProducts);
        setHeldSales(initialHeldSales);
        setBranchId(String(activeBranchId));
        setCart([]);
        setHeldSaleId(null);
        form.setData('client_request_id', newRequestId());
        form.clearErrors();
        scanRef.current?.focus();
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset cart when server branch stock reloads
    }, [activeBranchId, initialProducts, initialHeldSales]);

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

    const discountMinor = permissions.discount
        ? Math.min(
              Math.max(0, toMinor(form.data.discount_amount || '0', currency)),
              subtotalMinor,
          )
        : 0;
    const totalMinor = subtotalMinor - discountMinor;

    const permissionFloorFor = (line: CartLine): number => {
        if (permissions.approve_self) {
            return line.min_selling_price_minor;
        }

        const percent = Math.max(
            0,
            Math.min(100, permissions.negotiation_floor_percent ?? 100),
        );
        const floor = Math.floor(
            (line.list_unit_price_minor * percent) / 100,
        );

        return Math.max(line.min_selling_price_minor, floor);
    };

    const hasPriceBelowPermission = cart.some(
        (line) => line.unit_price_minor < permissionFloorFor(line),
    );
    const needsApproval =
        !permissions.approve_self &&
        (hasPriceBelowPermission || discountMinor > 0);

    const cashShareMinor = useMemo(() => {
        if (!splitMode) {
            return form.data.payment_method === 'cash' ? totalMinor : 0;
        }

        return payments
            .filter((payment) => payment.method === 'cash')
            .reduce(
                (sum, payment) => sum + toMinor(payment.amount || '0', currency),
                0,
            );
    }, [splitMode, payments, form.data.payment_method, totalMinor, currency]);

    const cashTenderedMinor = toMinor(cashTendered || '0', currency);
    const changeMinor = Math.max(0, cashTenderedMinor - cashShareMinor);

    useEffect(() => {
        if (!splitMode && payments.length === 1) {
            setPayments([
                {
                    ...payments[0],
                    method: form.data.payment_method,
                    amount: fromMinor(totalMinor, currency),
                },
            ]);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- sync single tender amount to total
    }, [totalMinor, splitMode, form.data.payment_method, currency]);

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
            setScanMessage(
                t('pages.pos.out_of_stock', 'No stock available for this item.'),
            );
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
                    list_unit_price_minor: product.selling_price_minor,
                    min_selling_price_minor:
                        product.min_selling_price_minor ?? 0,
                    is_negotiable: product.is_negotiable ?? true,
                },
            ];
        });
        setScanMessage(null);
    };

    const handleScanSubmit = async () => {
        const code = search.trim();

        if (code === '' || scanBusy) {
            return;
        }

        setScanBusy(true);
        setScanMessage(null);

        try {
            const local = products.find(
                (product) =>
                    product.barcode === code ||
                    product.sku.toLowerCase() === code.toLowerCase(),
            );

            if (local) {
                addProduct(local);
                setSearch('');
                setProducts(initialProducts);
                requestAnimationFrame(() => scanRef.current?.focus());
                return;
            }

            const response = await fetch(
                lookupBarcode.url({
                    query: {
                        barcode: code,
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

            if (response.status === 404) {
                setScanMessage(
                    t('pages.pos.barcode_not_found', 'Barcode not found.'),
                );
                setSearch('');
                requestAnimationFrame(() => scanRef.current?.focus());
                return;
            }

            if (!response.ok) {
                setScanMessage(
                    t('pages.pos.scan_failed', 'Scan failed. Try again.'),
                );
                return;
            }

            const payload = (await response.json()) as { product: PosProduct };
            addProduct(payload.product);
            setSearch('');
            setProducts(initialProducts);
            requestAnimationFrame(() => scanRef.current?.focus());
        } finally {
            setScanBusy(false);
        }
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

    const applyNegotiatedPrice = (productId: number) => {
        if (!permissions.negotiate) {
            return;
        }

        const line = cart.find((entry) => entry.product_id === productId);

        if (!line || (!line.is_negotiable && !permissions.approve_self)) {
            setEditingPriceId(null);
            setPriceDraft('');
            return;
        }

        const next = Math.max(
            line.min_selling_price_minor,
            toMinor(priceDraft || '0', currency),
        );
        setCart((current) =>
            current.map((entry) =>
                entry.product_id === productId
                    ? { ...entry, unit_price_minor: next }
                    : entry,
            ),
        );
        setEditingPriceId(null);
        setPriceDraft('');
        scanRef.current?.focus();
    };

    const createQuickCustomer = async () => {
        if (!permissions.create_customer || newCustomerName.trim() === '') {
            return;
        }

        setCreatingCustomer(true);

        try {
            const token = csrfToken();
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

    const buildSalePayload = (withApproval: boolean) => {
        const paymentRows = splitMode
            ? payments.map((payment) => {
                  const amountMinor = toMinor(payment.amount || '0', currency);
                  const isCash = payment.method === 'cash';

                  return {
                      method: payment.method,
                      amount: fromMinor(amountMinor, currency),
                      reference: payment.reference || null,
                      tendered_amount:
                          isCash && cashTenderedMinor > 0
                              ? fromMinor(cashTenderedMinor, currency)
                              : null,
                      change_amount:
                          isCash && changeMinor > 0
                              ? fromMinor(changeMinor, currency)
                              : null,
                  };
              })
            : [
                  {
                      method: form.data.payment_method,
                      amount: fromMinor(totalMinor, currency),
                      reference: form.data.payment_reference || null,
                      tendered_amount:
                          form.data.payment_method === 'cash' &&
                          cashTenderedMinor > 0
                              ? fromMinor(cashTenderedMinor, currency)
                              : null,
                      change_amount:
                          form.data.payment_method === 'cash' && changeMinor > 0
                              ? fromMinor(changeMinor, currency)
                              : null,
                  },
              ];

        return {
            branch_id: Number(branchId),
            customer_id:
                customerMode === 'saved' && form.data.customer_id !== ''
                    ? Number(form.data.customer_id)
                    : null,
            customer_name:
                customerMode === 'walkin'
                    ? form.data.customer_name || null
                    : null,
            payment_method: paymentRows[0]?.method ?? form.data.payment_method,
            payment_reference: paymentRows[0]?.reference ?? null,
            discount_amount: permissions.discount
                ? form.data.discount_amount || '0'
                : '0',
            notes: form.data.notes || null,
            client_request_id: form.data.client_request_id,
            held_sale_id: heldSaleId,
            cash_tendered:
                cashShareMinor > 0
                    ? fromMinor(cashTenderedMinor, currency)
                    : '0',
            change_given:
                cashShareMinor > 0 ? fromMinor(changeMinor, currency) : '0',
            manager_approval:
                withApproval && needsApproval
                    ? {
                          pin: managerPin,
                          login: managerLogin,
                          password: managerPassword,
                      }
                    : null,
            payments: paymentRows,
            items: cart.map((line) => ({
                product_id: line.product_id,
                quantity: line.quantity,
                unit_price: fromMinor(line.unit_price_minor, currency),
                list_unit_price: fromMinor(line.list_unit_price_minor, currency),
            })),
        };
    };

    const resetAfterSale = () => {
        setConfirmOpen(false);
        setApprovalOpen(false);
        setCart([]);
        setHeldSaleId(null);
        setHeldLabel('');
        setCashTendered('');
        setManagerLogin('');
        setManagerPassword('');
        setManagerPin('');
        setSplitMode(false);
        setPayments([
            {
                key: 'p1',
                method: paymentMethods[0]?.value ?? 'cash',
                amount: '',
                reference: '',
            },
        ]);
        form.setData('client_request_id', newRequestId());
        form.setData('discount_amount', '');
        form.setData('notes', '');
        form.setData('payment_reference', '');
        scanRef.current?.focus();
    };

    const submitSale = (withApproval = false) => {
        if (cart.length === 0 || form.processing) {
            return;
        }

        if (needsApproval && !withApproval) {
            setConfirmOpen(false);
            setPendingAction('sale');
            setApprovalOpen(true);
            return;
        }

        if (cashShareMinor > 0 && cashTenderedMinor < cashShareMinor) {
            form.setError(
                'cash_tendered',
                t(
                    'pages.pos.cash_short',
                    'Cash tendered must cover the cash portion.',
                ),
            );
            return;
        }

        const payload = buildSalePayload(withApproval);
        form.transform(() => payload);
        form.post(storeSale.url(), {
            preserveScroll: true,
            onSuccess: resetAfterSale,
            onError: () => {
                setConfirmOpen(false);
                if (needsApproval) {
                    setApprovalOpen(true);
                }
            },
        });
    };

    const submitHold = (withApproval = false) => {
        if (cart.length === 0 || form.processing || !permissions.hold) {
            return;
        }

        if (needsApproval && !withApproval) {
            setPendingAction('hold');
            setApprovalOpen(true);
            return;
        }

        const payload = {
            ...buildSalePayload(withApproval),
            held_label: heldLabel || null,
        };

        form.transform(() => payload);
        form.post(holdSale.url(), {
            preserveScroll: true,
            onSuccess: () => {
                resetAfterSale();
                router.reload({ only: ['heldSales', 'products'] });
            },
        });
    };

    const resumeSale = async (saleId: number) => {
        const token = csrfToken();
        const response = await fetch(resumeHeld.url(saleId), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const payload = (await response.json()) as {
            sale: HeldSale & {
                items: Array<{
                    product_id: number;
                    name: string;
                    sku: string;
                    quantity: number;
                    max_quantity: number;
                    unit_price_minor: number;
                    list_unit_price_minor: number;
                    min_selling_price_minor?: number;
                    is_negotiable?: boolean;
                }>;
                discount_amount_minor: number;
                notes: string | null;
                customer_id: number | null;
                customer_name: string | null;
            };
        };

        setCart(
            payload.sale.items.map((item) => ({
                product_id: item.product_id,
                name: item.name,
                sku: item.sku,
                quantity: item.quantity,
                max_quantity: item.max_quantity,
                unit_price_minor: item.unit_price_minor,
                list_unit_price_minor: item.list_unit_price_minor,
                min_selling_price_minor: item.min_selling_price_minor ?? 0,
                is_negotiable: item.is_negotiable ?? true,
            })),
        );
        setHeldSaleId(payload.sale.id);
        setHeldLabel(payload.sale.held_label ?? '');
        form.setData(
            'discount_amount',
            payload.sale.discount_amount_minor > 0
                ? fromMinor(payload.sale.discount_amount_minor, currency)
                : '',
        );
        form.setData('notes', payload.sale.notes ?? '');

        if (payload.sale.customer_id) {
            setCustomerMode('saved');
            form.setData('customer_id', payload.sale.customer_id);
        } else {
            setCustomerMode('walkin');
            form.setData('customer_name', payload.sale.customer_name ?? '');
        }

        setHeldOpen(false);
        scanRef.current?.focus();
    };

    const removeHeld = (saleId: number) => {
        router.delete(discardHeld.url(saleId), {
            preserveScroll: true,
            onSuccess: () => {
                setHeldSales((current) =>
                    current.filter((sale) => sale.id !== saleId),
                );
            },
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
                                'Scan products, tender payment, and finish without leaving the cart.',
                            )}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="rounded-xl"
                            onClick={() => setHeldOpen(true)}
                        >
                            <Play className="size-4" />
                            {t('pages.pos.resume', 'Resume')}
                            {heldSales.length > 0 ? (
                                <Badge variant="secondary" className="ml-1">
                                    {heldSales.length}
                                </Badge>
                            ) : null}
                        </Button>
                        <Button variant="outline" asChild className="rounded-xl">
                            <Link href={salesIndex()}>
                                {t('pages.pos.history', 'Sales history')}
                            </Link>
                        </Button>
                    </div>
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
                                        'Scan barcode or search name / SKU',
                                    )}
                                </Label>
                                <form
                                    className="relative"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        void handleScanSubmit();
                                    }}
                                >
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        ref={scanRef}
                                        id="pos_search"
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder={t(
                                            'pages.pos.search_placeholder',
                                            'Scan or type, then Enter…',
                                        )}
                                        className="h-12 rounded-xl pl-10 text-base"
                                        autoFocus
                                        autoComplete="off"
                                    />
                                </form>
                                {scanMessage ? (
                                    <p className="text-xs text-destructive">
                                        {scanMessage}
                                    </p>
                                ) : (
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'pages.pos.scan_hint',
                                            'Enter adds the barcode immediately and keeps the scanner ready.',
                                        )}
                                    </p>
                                )}
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
                                            onClick={() => {
                                                addProduct(product);
                                                scanRef.current?.focus();
                                            }}
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
                        <div className="flex items-start justify-between gap-2">
                            <div>
                                <h2 className="font-display text-lg font-semibold">
                                    {t('pages.pos.cart', 'Cart')}
                                    {heldSaleId ? (
                                        <Badge className="ml-2" variant="outline">
                                            {t('pages.pos.resumed', 'Resumed')}
                                        </Badge>
                                    ) : null}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'pages.pos.cart_hint',
                                        'Tap a line price to negotiate. Scanning stays focused.',
                                    )}
                                </p>
                            </div>
                        </div>

                        <div className="min-h-0 flex-1 space-y-2 overflow-auto">
                            {cart.length === 0 ? (
                                <p className="rounded-xl border border-dashed border-border px-4 py-10 text-center text-sm text-muted-foreground">
                                    {t(
                                        'pages.pos.empty_cart',
                                        'Scan a barcode or tap a product to start.',
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
                                            <div className="text-right">
                                                {permissions.negotiate &&
                                                (line.is_negotiable ||
                                                    permissions.approve_self) &&
                                                editingPriceId ===
                                                    line.product_id ? (
                                                    <div className="flex items-center gap-1">
                                                        <Input
                                                            value={priceDraft}
                                                            onChange={(event) =>
                                                                setPriceDraft(
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            onKeyDown={(
                                                                event,
                                                            ) => {
                                                                if (
                                                                    event.key ===
                                                                    'Enter'
                                                                ) {
                                                                    applyNegotiatedPrice(
                                                                        line.product_id,
                                                                    );
                                                                }
                                                            }}
                                                            className="h-9 w-24 rounded-lg text-right"
                                                            autoFocus
                                                        />
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            className="h-9 rounded-lg"
                                                            onClick={() =>
                                                                applyNegotiatedPrice(
                                                                    line.product_id,
                                                                )
                                                            }
                                                        >
                                                            OK
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        className="font-semibold hover:text-primary disabled:cursor-default disabled:hover:text-inherit"
                                                        disabled={
                                                            !permissions.negotiate ||
                                                            (!line.is_negotiable &&
                                                                !permissions.approve_self)
                                                        }
                                                        onClick={() => {
                                                            if (
                                                                !permissions.negotiate ||
                                                                (!line.is_negotiable &&
                                                                    !permissions.approve_self)
                                                            ) {
                                                                return;
                                                            }
                                                            setEditingPriceId(
                                                                line.product_id,
                                                            );
                                                            setPriceDraft(
                                                                fromMinor(
                                                                    line.unit_price_minor,
                                                                    currency,
                                                                ),
                                                            );
                                                        }}
                                                        title={
                                                            line.is_negotiable ||
                                                            permissions.approve_self
                                                                ? t(
                                                                      'pages.pos.negotiate_price',
                                                                      'Edit line price',
                                                                  )
                                                                : t(
                                                                      'pages.pos.not_negotiable',
                                                                      'Not negotiable',
                                                                  )
                                                        }
                                                    >
                                                        {formatMoney(
                                                            line.unit_price_minor *
                                                                line.quantity,
                                                            currency,
                                                            locale,
                                                        )}
                                                    </button>
                                                )}
                                                {line.unit_price_minor !==
                                                line.list_unit_price_minor ? (
                                                    <div className="text-xs text-amber-700 dark:text-amber-300">
                                                        {t(
                                                            'pages.pos.list_price',
                                                            'List',
                                                        )}{' '}
                                                        {formatMoney(
                                                            line.list_unit_price_minor,
                                                            currency,
                                                            locale,
                                                        )}
                                                    </div>
                                                ) : null}
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

                            <div className="flex items-center justify-between gap-2">
                                <Label>
                                    {t('pages.pos.payment', 'Payment')}
                                </Label>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={splitMode ? 'default' : 'outline'}
                                    className="rounded-lg"
                                    onClick={() => {
                                        const next = !splitMode;
                                        setSplitMode(next);
                                        if (next) {
                                            const half = Math.floor(
                                                totalMinor / 2,
                                            );
                                            setPayments([
                                                {
                                                    key: 'p1',
                                                    method: 'cash',
                                                    amount: fromMinor(
                                                        half,
                                                        currency,
                                                    ),
                                                    reference: '',
                                                },
                                                {
                                                    key: 'p2',
                                                    method:
                                                        paymentMethods.find(
                                                            (method) =>
                                                                method.value !==
                                                                'cash',
                                                        )?.value ??
                                                        'mobile_money',
                                                    amount: fromMinor(
                                                        totalMinor - half,
                                                        currency,
                                                    ),
                                                    reference: '',
                                                },
                                            ]);
                                        }
                                    }}
                                >
                                    <Split className="size-3.5" />
                                    {t('pages.pos.split', 'Split')}
                                </Button>
                            </div>

                            {splitMode ? (
                                <div className="space-y-2">
                                    {payments.map((payment, index) => (
                                        <div
                                            key={payment.key}
                                            className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                                        >
                                            <select
                                                value={payment.method}
                                                onChange={(event) =>
                                                    setPayments((current) =>
                                                        current.map((row, rowIndex) =>
                                                            rowIndex === index
                                                                ? {
                                                                      ...row,
                                                                      method: event
                                                                          .target
                                                                          .value,
                                                                  }
                                                                : row,
                                                        ),
                                                    )
                                                }
                                                className="h-11 rounded-xl border border-input bg-background px-3 text-base"
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
                                            <Input
                                                value={payment.amount}
                                                onChange={(event) =>
                                                    setPayments((current) =>
                                                        current.map((row, rowIndex) =>
                                                            rowIndex === index
                                                                ? {
                                                                      ...row,
                                                                      amount: event
                                                                          .target
                                                                          .value,
                                                                  }
                                                                : row,
                                                        ),
                                                    )
                                                }
                                                className="h-11 rounded-xl"
                                                placeholder={t(
                                                    'pages.pos.amount',
                                                    'Amount',
                                                )}
                                            />
                                            {payments.length > 1 ? (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    className="h-11 rounded-xl"
                                                    onClick={() =>
                                                        setPayments((current) =>
                                                            current.filter(
                                                                (_, rowIndex) =>
                                                                    rowIndex !==
                                                                    index,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            ) : (
                                                <div />
                                            )}
                                        </div>
                                    ))}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-10 w-full rounded-xl"
                                        onClick={() =>
                                            setPayments((current) => [
                                                ...current,
                                                {
                                                    key: `p${Date.now()}`,
                                                    method:
                                                        paymentMethods[0]
                                                            ?.value ?? 'cash',
                                                    amount: '0',
                                                    reference: '',
                                                },
                                            ])
                                        }
                                    >
                                        {t(
                                            'pages.pos.add_payment',
                                            'Add payment',
                                        )}
                                    </Button>
                                </div>
                            ) : (
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <div className="space-y-1.5">
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
                                        <Input
                                            id="payment_reference"
                                            value={form.data.payment_reference}
                                            onChange={(event) =>
                                                form.setData(
                                                    'payment_reference',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder={t(
                                                'pages.pos.reference',
                                                'Reference',
                                            )}
                                            className="h-11 rounded-xl"
                                        />
                                    </div>
                                </div>
                            )}

                            {cashShareMinor > 0 ? (
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="cash_tendered">
                                            {t(
                                                'pages.pos.cash_tendered',
                                                'Cash tendered',
                                            )}
                                        </Label>
                                        <Input
                                            id="cash_tendered"
                                            value={cashTendered}
                                            onChange={(event) =>
                                                setCashTendered(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder={fromMinor(
                                                cashShareMinor,
                                                currency,
                                            )}
                                            className="h-11 rounded-xl"
                                        />
                                        <InputError
                                            message={form.errors.cash_tendered}
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>
                                            {t('pages.pos.change', 'Change')}
                                        </Label>
                                        <div className="flex h-11 items-center rounded-xl border border-border/70 bg-muted/40 px-3 text-base font-semibold">
                                            {formatMoney(
                                                changeMinor,
                                                currency,
                                                locale,
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ) : null}

                            {permissions.discount ? (
                                <div className="space-y-1.5">
                                    <Label htmlFor="discount_amount">
                                        {t('pages.pos.discount', 'Discount')}
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

                            <div className="grid grid-cols-2 gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="h-14 rounded-2xl text-base"
                                    disabled={
                                        cart.length === 0 ||
                                        form.processing ||
                                        !permissions.hold
                                    }
                                    onClick={submitHold}
                                >
                                    <Pause className="size-4" />
                                    {t('pages.pos.hold', 'Hold')}
                                </Button>
                                <Button
                                    type="button"
                                    className="h-14 rounded-2xl text-base"
                                    disabled={
                                        cart.length === 0 || form.processing
                                    }
                                    onClick={() => setConfirmOpen(true)}
                                >
                                    {form.processing
                                        ? t(
                                              'pages.pos.processing',
                                              'Processing…',
                                          )
                                        : t(
                                              'pages.pos.complete',
                                              'Complete sale',
                                          )}
                                </Button>
                            </div>
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
                                <span>{t('pages.pos.items', 'Items')}</span>
                                <span>{cart.length}</span>
                            </div>
                            {cashShareMinor > 0 ? (
                                <>
                                    <div className="flex justify-between">
                                        <span>
                                            {t(
                                                'pages.pos.cash_tendered',
                                                'Cash tendered',
                                            )}
                                        </span>
                                        <span>
                                            {formatMoney(
                                                cashTenderedMinor,
                                                currency,
                                                locale,
                                            )}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>
                                            {t('pages.pos.change', 'Change')}
                                        </span>
                                        <span>
                                            {formatMoney(
                                                changeMinor,
                                                currency,
                                                locale,
                                            )}
                                        </span>
                                    </div>
                                </>
                            ) : null}
                            <div className="flex justify-between text-base font-semibold">
                                <span>{t('pages.pos.total', 'Total')}</span>
                                <span>
                                    {formatMoney(
                                        totalMinor,
                                        currency,
                                        locale,
                                    )}
                                </span>
                            </div>
                            {needsApproval ? (
                                <p className="text-xs text-amber-700 dark:text-amber-300">
                                    {t(
                                        'pages.pos.approval_needed',
                                        'Manager PIN required when price falls below your permission floor, or for discounts.',
                                    )}
                                </p>
                            ) : null}
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="h-12 rounded-xl"
                                onClick={() => setConfirmOpen(false)}
                                disabled={form.processing}
                            >
                                {t('pages.pos.cancel', 'Cancel')}
                            </Button>
                            <Button
                                type="button"
                                className="h-12 rounded-xl"
                                onClick={() => submitSale(false)}
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? t('pages.pos.saving', 'Saving…')
                                    : t(
                                          'pages.pos.confirm_charge',
                                          'Confirm & charge',
                                      )}
                            </Button>
                        </div>
                    </div>
                </div>
            ) : null}

            {approvalOpen ? (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center">
                    <div className="w-full max-w-md space-y-4 rounded-2xl bg-background p-5 shadow-xl">
                        <div>
                            <h3 className="font-display text-xl font-semibold">
                                {t(
                                    'pages.pos.manager_approval',
                                    'Manager approval',
                                )}
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'pages.pos.manager_approval_hint',
                                    'Enter a manager PIN, or an owner/manager login and password.',
                                )}
                            </p>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="manager_pin">
                                {t('pages.pos.manager_pin', 'Manager PIN')}
                            </Label>
                            <Input
                                id="manager_pin"
                                value={managerPin}
                                onChange={(event) =>
                                    setManagerPin(event.target.value)
                                }
                                className="h-11 rounded-xl"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={8}
                                placeholder="4–8 digits"
                            />
                            <InputError
                                message={form.errors['manager_approval.pin']}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="manager_login">
                                {t('pages.pos.manager_login', 'Manager login')}
                            </Label>
                            <Input
                                id="manager_login"
                                value={managerLogin}
                                onChange={(event) =>
                                    setManagerLogin(event.target.value)
                                }
                                className="h-11 rounded-xl"
                                autoComplete="username"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="manager_password">
                                {t('pages.pos.manager_password', 'Password')}
                            </Label>
                            <Input
                                id="manager_password"
                                type="password"
                                value={managerPassword}
                                onChange={(event) =>
                                    setManagerPassword(event.target.value)
                                }
                                className="h-11 rounded-xl"
                                autoComplete="current-password"
                            />
                            <InputError
                                message={
                                    form.errors['manager_approval.password'] ??
                                    form.errors.manager_approval
                                }
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="h-12 rounded-xl"
                                onClick={() => setApprovalOpen(false)}
                            >
                                {t('pages.pos.cancel', 'Cancel')}
                            </Button>
                            <Button
                                type="button"
                                className="h-12 rounded-xl"
                                onClick={() => {
                                    if (pendingAction === 'hold') {
                                        submitHold(true);
                                    } else {
                                        submitSale(true);
                                    }
                                }}
                                disabled={form.processing}
                            >
                                {pendingAction === 'hold'
                                    ? t(
                                          'pages.pos.approve_hold',
                                          'Approve & hold',
                                      )
                                    : t(
                                          'pages.pos.approve_charge',
                                          'Approve & charge',
                                      )}
                            </Button>
                        </div>
                    </div>
                </div>
            ) : null}

            {heldOpen ? (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center">
                    <div className="max-h-[80vh] w-full max-w-lg overflow-auto rounded-2xl bg-background p-5 shadow-xl">
                        <div className="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h3 className="font-display text-xl font-semibold">
                                    {t('pages.pos.held_sales', 'Held sales')}
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'pages.pos.held_sales_hint',
                                        'Resume a parked cart for this branch.',
                                    )}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setHeldOpen(false)}
                                className="rounded-lg p-2 hover:bg-muted"
                                aria-label="Close"
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                        {heldSales.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                {t(
                                    'pages.pos.no_held',
                                    'No held sales for this branch.',
                                )}
                            </p>
                        ) : (
                            <div className="space-y-2">
                                {heldSales.map((sale) => (
                                    <div
                                        key={sale.id}
                                        className="flex items-center justify-between gap-3 rounded-xl border border-border/70 p-3"
                                    >
                                        <div>
                                            <div className="font-medium">
                                                {sale.held_label ||
                                                    sale.sale_number}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {sale.sale_number} ·{' '}
                                                {sale.item_count}{' '}
                                                {t('pages.pos.items', 'items')}{' '}
                                                · {sale.total_formatted}
                                            </div>
                                        </div>
                                        <div className="flex gap-1">
                                            <Button
                                                type="button"
                                                size="sm"
                                                className="rounded-lg"
                                                onClick={() =>
                                                    void resumeSale(sale.id)
                                                }
                                            >
                                                {t(
                                                    'pages.pos.resume',
                                                    'Resume',
                                                )}
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                className="rounded-lg"
                                                onClick={() =>
                                                    removeHeld(sale.id)
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
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
