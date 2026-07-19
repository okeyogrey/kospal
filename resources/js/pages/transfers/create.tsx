import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as transfersIndex,
    store,
} from '@/routes/stock-transfers';

type Option = { id: number; name: string; sku?: string | null };

type StockRow = {
    branch_id: number;
    product_id: number;
    product_name: string | null;
    sku: string | null;
    quantity: number;
};

type LineItem = {
    product_id: string;
    quantity: string;
};

export default function TransfersCreate({
    branches,
    products,
    stockByBranch,
    defaultSourceBranchId,
}: {
    branches: Option[];
    products: Option[];
    stockByBranch: StockRow[];
    defaultSourceBranchId: number | null;
}) {
    const form = useForm({
        source_branch_id: String(
            defaultSourceBranchId ?? branches[0]?.id ?? '',
        ),
        destination_branch_id: String(
            branches.find((b) => b.id !== defaultSourceBranchId)?.id ??
                branches[1]?.id ??
                '',
        ),
        notes: '',
        items: [{ product_id: '', quantity: '1' }] as LineItem[],
    });

    const availableForSource = stockByBranch.filter(
        (row) => String(row.branch_id) === form.data.source_branch_id,
    );

    const availableForProduct = (productId: string): number => {
        const row = availableForSource.find(
            (item) => String(item.product_id) === productId,
        );

        return row?.quantity ?? 0;
    };

    const addLine = () => {
        form.setData('items', [
            ...form.data.items,
            { product_id: '', quantity: '1' },
        ]);
    };

    const removeLine = (index: number) => {
        form.setData(
            'items',
            form.data.items.filter((_, i) => i !== index),
        );
    };

    const updateLine = (
        index: number,
        field: keyof LineItem,
        value: string,
    ) => {
        form.setData(
            'items',
            form.data.items.map((item, i) =>
                i === index ? { ...item, [field]: value } : item,
            ),
        );
    };

    return (
        <>
            <Head title="Create transfer" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Create transfer
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Drafts do not change stock until you dispatch.
                        </p>
                    </div>
                    <Button variant="outline" asChild className="w-full sm:w-auto">
                        <Link href={transfersIndex()}>Back to transfers</Link>
                    </Button>
                </div>

                <form
                    className="mx-auto w-full max-w-3xl space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            source_branch_id: Number(data.source_branch_id),
                            destination_branch_id: Number(
                                data.destination_branch_id,
                            ),
                            items: data.items.map((item) => ({
                                product_id: Number(item.product_id),
                                quantity: Number(item.quantity),
                            })),
                        }));
                        form.post(store.url());
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="source_branch_id">
                                Source branch
                            </Label>
                            <select
                                id="source_branch_id"
                                value={form.data.source_branch_id}
                                onChange={(event) =>
                                    form.setData(
                                        'source_branch_id',
                                        event.target.value,
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                <option value="">Select source</option>
                                {branches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>
                                        {branch.name}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={form.errors.source_branch_id}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="destination_branch_id">
                                Destination branch
                            </Label>
                            <select
                                id="destination_branch_id"
                                value={form.data.destination_branch_id}
                                onChange={(event) =>
                                    form.setData(
                                        'destination_branch_id',
                                        event.target.value,
                                    )
                                }
                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                <option value="">Select destination</option>
                                {branches.map((branch) => (
                                    <option
                                        key={branch.id}
                                        value={branch.id}
                                        disabled={
                                            String(branch.id) ===
                                            form.data.source_branch_id
                                        }
                                    >
                                        {branch.name}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={form.errors.destination_branch_id}
                            />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            rows={3}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            placeholder="Optional transfer notes"
                        />
                        <InputError message={form.errors.notes} />
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-sm font-medium">Products</h2>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addLine}
                            >
                                <Plus className="size-4" />
                                Add line
                            </Button>
                        </div>
                        <InputError message={form.errors.items} />

                        <div className="space-y-3">
                            {form.data.items.map((item, index) => {
                                const available = availableForProduct(
                                    item.product_id,
                                );

                                return (
                                    <div
                                        key={index}
                                        className="grid gap-2 rounded-lg border border-border/80 p-3 sm:grid-cols-[1fr_8rem_auto]"
                                    >
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`product-${index}`}
                                            >
                                                Product
                                            </Label>
                                            <select
                                                id={`product-${index}`}
                                                value={item.product_id}
                                                onChange={(event) =>
                                                    updateLine(
                                                        index,
                                                        'product_id',
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                            >
                                                <option value="">
                                                    Select product
                                                </option>
                                                {products.map((product) => {
                                                    const qty =
                                                        availableForProduct(
                                                            String(product.id),
                                                        );

                                                    return (
                                                        <option
                                                            key={product.id}
                                                            value={product.id}
                                                            disabled={qty < 1}
                                                        >
                                                            {product.name}
                                                            {product.sku
                                                                ? ` (${product.sku})`
                                                                : ''}
                                                            {form.data
                                                                .source_branch_id
                                                                ? ` · avail ${qty}`
                                                                : ''}
                                                        </option>
                                                    );
                                                })}
                                            </select>
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `items.${index}.product_id`
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`quantity-${index}`}
                                            >
                                                Qty
                                                {item.product_id
                                                    ? ` (max ${available})`
                                                    : ''}
                                            </Label>
                                            <Input
                                                id={`quantity-${index}`}
                                                type="number"
                                                min={1}
                                                max={
                                                    available > 0
                                                        ? available
                                                        : undefined
                                                }
                                                value={item.quantity}
                                                onChange={(event) =>
                                                    updateLine(
                                                        index,
                                                        'quantity',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `items.${index}.quantity`
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="flex items-end">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={
                                                    form.data.items.length ===
                                                    1
                                                }
                                                onClick={() =>
                                                    removeLine(index)
                                                }
                                                aria-label="Remove line"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            asChild
                            className="w-full sm:w-auto"
                        >
                            <Link href={transfersIndex()}>Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="w-full sm:w-auto"
                        >
                            Save draft
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
