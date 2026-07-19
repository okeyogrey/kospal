import { useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export type ProductOption = {
    id: number;
    name: string;
    sku?: string | null;
    barcode?: string | null;
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
    id = 'product_search',
    label = 'Product',
    products,
    value,
    onChange,
    placeholder = 'Search by name, SKU, or barcode…',
    required = false,
    error,
    className,
}: Props) {
    const [query, setQuery] = useState('');
    const selected = products.find((product) => String(product.id) === String(value));

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();

        if (term === '') {
            return products.slice(0, 40);
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
            .slice(0, 40);
    }, [products, query]);

    return (
        <div className={cn('grid gap-2', className)}>
            {label ? <Label htmlFor={id}>{label}</Label> : null}
            <Input
                id={id}
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={placeholder}
                autoComplete="off"
            />
            {selected ? (
                <p className="text-xs text-muted-foreground">
                    Selected:{' '}
                    <span className="font-medium text-foreground">
                        {selected.name}
                        {selected.sku ? ` (${selected.sku})` : ''}
                    </span>
                </p>
            ) : null}
            <select
                value={value === '' || value === null || value === undefined ? '' : String(value)}
                onChange={(event) => onChange(event.target.value)}
                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                required={required}
                size={Math.min(6, Math.max(3, filtered.length || 3))}
            >
                <option value="">Select a product</option>
                {filtered.map((product) => (
                    <option key={product.id} value={product.id}>
                        {product.name}
                        {product.sku ? ` · ${product.sku}` : ''}
                    </option>
                ))}
            </select>
            {filtered.length === 0 ? (
                <p className="text-xs text-muted-foreground">No products match your search.</p>
            ) : null}
            {error ? <p className="text-sm text-destructive">{error}</p> : null}
        </div>
    );
}
