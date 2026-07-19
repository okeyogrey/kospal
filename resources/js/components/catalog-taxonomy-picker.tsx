import { Label } from '@/components/ui/label';

export type CatalogTaxonomy = {
    categories: { id: number; name: string }[];
};

type CategorySelectProps = {
    taxonomy: CatalogTaxonomy;
    value: string | number;
    onChange: (value: string) => void;
    idPrefix?: string;
    label?: string;
    required?: boolean;
};

const selectClassName =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm';

export function CategorySelect({
    taxonomy,
    value,
    onChange,
    idPrefix = 'category',
    label = 'Category',
    required = false,
}: CategorySelectProps) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`${idPrefix}-category`}>{label}</Label>
            <select
                id={`${idPrefix}-category`}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className={selectClassName}
                required={required}
            >
                <option value="">Select category</option>
                {taxonomy.categories.map((category) => (
                    <option key={category.id} value={category.id}>
                        {category.name}
                    </option>
                ))}
            </select>
        </div>
    );
}

/** @deprecated Use CategorySelect instead. */
export function CatalogTaxonomyPicker({
    taxonomy,
    categoryId,
    brandId,
    onCategoryChange,
    onBrandChange,
    idPrefix = 'catalog',
}: {
    taxonomy: CatalogTaxonomy;
    categoryId?: string | number;
    subcategoryId?: string | number;
    subSubcategoryId?: string | number;
    brandId?: string | number;
    onCategoryChange?: (value: string) => void;
    onSubcategoryChange?: (value: string) => void;
    onSubSubcategoryChange?: (value: string) => void;
    onBrandChange?: (value: string) => void;
    idPrefix?: string;
    showBrand?: boolean;
}) {
    const resolvedValue = categoryId || brandId || '';

    return (
        <CategorySelect
            taxonomy={taxonomy}
            value={resolvedValue}
            onChange={(next) => {
                onCategoryChange?.(next);
                onBrandChange?.(next);
            }}
            idPrefix={idPrefix}
        />
    );
}
