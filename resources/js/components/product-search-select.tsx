import { Check, Package, Search, X } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export type ProductOption = {
    id: number;
    name: string;
    sku?: string | null;
    barcode?: string | null;
    base_unit_name?: string | null;
    packs?: Array<{
        id: number;
        name: string;
        units_per_pack: number;
        barcode?: string | null;
    }>;
};

type Props = {
    id?: string;
    label?: string;
    products: ProductOption[];
    value: string | number;
    onChange: (productId: string) => void;
    placeholder?: string;
    required?: boolean;
    error?: string;
    className?: string;
};

export function ProductSearchSelect({
    id,
    label = 'Product',
    products,
    value,
    onChange,
    placeholder = 'Search by name, SKU, or barcode…',
    required = false,
    error,
    className,
}: Props) {
    const generatedId = useId();
    const searchId = id ?? generatedId;
    const listId = `${searchId}-list`;
    const inputRef = useRef<HTMLInputElement>(null);
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);

    const selected = products.find(
        (product) => String(product.id) === String(value),
    );

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();

        if (term === '') {
            return products.slice(0, 50);
        }

        return products
            .filter((product) => {
                const haystack = [
                    product.name,
                    product.sku ?? '',
                    product.barcode ?? '',
                ]
                    .join(' ')
                    .toLowerCase();

                return haystack.includes(term);
            })
            .slice(0, 50);
    }, [products, query]);

    useEffect(() => {
        setActiveIndex(0);
    }, [query]);

    const selectProduct = (productId: number) => {
        onChange(String(productId));
        setQuery('');
        inputRef.current?.blur();
    };

    const clearSelection = () => {
        onChange('');
        setQuery('');
        inputRef.current?.focus();
    };

    return (
        <div className={cn('grid gap-2', className)}>
            {label ? <Label htmlFor={searchId}>{label}</Label> : null}

            {selected ? (
                <div className="flex items-start gap-3 rounded-xl border border-primary/40 bg-primary/10 px-3 py-3">
                    <div className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/15 text-primary">
                        <Package className="size-4" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium">
                            {selected.name}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            {[selected.sku, selected.barcode]
                                .filter(Boolean)
                                .join(' · ') || 'No SKU'}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={clearSelection}
                        className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-background/60 hover:text-foreground"
                        aria-label="Clear selected product"
                    >
                        <X className="size-4" />
                    </button>
                </div>
            ) : null}

            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    ref={inputRef}
                    id={searchId}
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    onKeyDown={(event) => {
                        if (filtered.length === 0) {
                            return;
                        }

                        if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            setActiveIndex((index) =>
                                Math.min(index + 1, filtered.length - 1),
                            );
                        }

                        if (event.key === 'ArrowUp') {
                            event.preventDefault();
                            setActiveIndex((index) => Math.max(index - 1, 0));
                        }

                        if (event.key === 'Enter') {
                            event.preventDefault();
                            const product = filtered[activeIndex];
                            if (product) {
                                selectProduct(product.id);
                            }
                        }
                    }}
                    placeholder={
                        selected
                            ? 'Search to change product…'
                            : placeholder
                    }
                    autoComplete="off"
                    role="combobox"
                    aria-expanded="true"
                    aria-controls={listId}
                    aria-autocomplete="list"
                    className="pl-9"
                />
            </div>

            <div
                id={listId}
                role="listbox"
                aria-label="Products"
                className="max-h-56 overflow-y-auto rounded-xl border border-border/80 bg-background/40"
            >
                {filtered.length === 0 ? (
                    <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                        No products match your search.
                    </p>
                ) : (
                    <ul className="divide-y divide-border/60">
                        {filtered.map((product, index) => {
                            const isSelected =
                                String(product.id) === String(value);
                            const isActive = index === activeIndex;

                            return (
                                <li key={product.id}>
                                    <button
                                        type="button"
                                        role="option"
                                        aria-selected={isSelected}
                                        onMouseEnter={() =>
                                            setActiveIndex(index)
                                        }
                                        onClick={() =>
                                            selectProduct(product.id)
                                        }
                                        className={cn(
                                            'flex w-full items-center gap-3 px-3 py-2.5 text-left transition-colors',
                                            isSelected
                                                ? 'bg-primary/15'
                                                : isActive
                                                  ? 'bg-muted/70'
                                                  : 'hover:bg-muted/50',
                                        )}
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {product.name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {[product.sku, product.barcode]
                                                    .filter(Boolean)
                                                    .join(' · ') || 'No SKU'}
                                            </p>
                                        </div>
                                        {isSelected ? (
                                            <Check className="size-4 shrink-0 text-primary" />
                                        ) : null}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            <input
                type="hidden"
                name={searchId}
                value={value === '' || value == null ? '' : String(value)}
                required={required}
                readOnly
            />

            {error ? <p className="text-sm text-destructive">{error}</p> : null}
        </div>
    );
}
