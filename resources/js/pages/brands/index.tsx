import { Form, Head, useForm } from '@inertiajs/react';
import { Award } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    CatalogTaxonomyPicker,
    type CatalogTaxonomy,
} from '@/components/catalog-taxonomy-picker';
import InputError from '@/components/input-error';
import { EmptyState } from '@/components/states';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy,
    index as brandsIndex,
    store,
    update,
} from '@/routes/brands';

type BrandRow = {
    id: number;
    name: string;
    description: string | null;
    category_id: number;
    category_path: string | null;
    is_active: boolean;
    products_count: number;
};

export default function BrandsIndex({
    brands,
    taxonomy,
}: {
    brands: BrandRow[];
    taxonomy: CatalogTaxonomy;
}) {
    const [editingId, setEditingId] = useState<number | null>(null);

    const subSubcategories = useMemo(
        () => taxonomy.categories.filter((row) => row.depth === 2),
        [taxonomy.categories],
    );

    const createForm = useForm({
        name: '',
        description: '',
        category_id: '' as string | number,
        categoryId: '',
        subcategoryId: '',
        subSubcategoryId: '',
        is_active: true,
    });

    const editForm = useForm({
        name: '',
        description: '',
        category_id: '' as string | number,
        categoryId: '',
        subcategoryId: '',
        subSubcategoryId: '',
        is_active: true,
    });

    const syncPickerToCategoryId = (
        form: typeof createForm | typeof editForm,
        subSubcategoryId: string,
    ) => {
        form.setData({
            ...form.data,
            subSubcategoryId,
            category_id: subSubcategoryId,
        });
    };

    const startEdit = (brand: BrandRow) => {
        setEditingId(brand.id);
        const subSubcategory = taxonomy.categories.find(
            (row) => row.id === brand.category_id,
        );
        const subcategory = subSubcategory?.parent_id
            ? taxonomy.categories.find(
                  (row) => row.id === subSubcategory.parent_id,
              )
            : undefined;
        const category = subcategory?.parent_id
            ? taxonomy.categories.find(
                  (row) => row.id === subcategory.parent_id,
              )
            : undefined;

        editForm.setData({
            name: brand.name,
            description: brand.description ?? '',
            category_id: brand.category_id,
            categoryId: category ? String(category.id) : '',
            subcategoryId: subcategory ? String(subcategory.id) : '',
            subSubcategoryId: String(brand.category_id),
            is_active: brand.is_active,
        });
        editForm.clearErrors();
    };

    return (
        <>
            <Head title="Brands" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold">
                        Brands
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Add brands under sub-subcategories such as Sneakers or
                        T-shirts.
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
                    <div className="rounded-2xl border border-border/80 bg-card/80">
                        {brands.length === 0 ? (
                            <EmptyState
                                title="No brands yet"
                                description="Create a sub-subcategory first, then add brands like Nike or Adidas."
                                icon={<Award className="size-5" />}
                            />
                        ) : (
                            <ul className="divide-y divide-border/70">
                                {brands.map((brand) => (
                                    <li key={brand.id} className="p-4">
                                        {editingId === brand.id ? (
                                            <form
                                                className="space-y-3"
                                                onSubmit={(event) => {
                                                    event.preventDefault();
                                                    editForm.patch(
                                                        update.url(brand.id),
                                                        {
                                                            preserveScroll:
                                                                true,
                                                            onSuccess: () =>
                                                                setEditingId(
                                                                    null,
                                                                ),
                                                        },
                                                    );
                                                }}
                                            >
                                                <div className="grid gap-2">
                                                    <Label>Name</Label>
                                                    <Input
                                                        value={
                                                            editForm.data.name
                                                        }
                                                        onChange={(e) =>
                                                            editForm.setData(
                                                                'name',
                                                                e.target.value,
                                                            )
                                                        }
                                                        required
                                                    />
                                                    <InputError
                                                        message={
                                                            editForm.errors
                                                                .name
                                                        }
                                                    />
                                                </div>
                                                <CatalogTaxonomyPicker
                                                    taxonomy={taxonomy}
                                                    categoryId={
                                                        editForm.data
                                                            .categoryId
                                                    }
                                                    subcategoryId={
                                                        editForm.data
                                                            .subcategoryId
                                                    }
                                                    subSubcategoryId={
                                                        editForm.data
                                                            .subSubcategoryId
                                                    }
                                                    brandId=""
                                                    onCategoryChange={(
                                                        value,
                                                    ) =>
                                                        editForm.setData({
                                                            ...editForm.data,
                                                            categoryId: value,
                                                            subcategoryId: '',
                                                            subSubcategoryId:
                                                                '',
                                                            category_id: '',
                                                        })
                                                    }
                                                    onSubcategoryChange={(
                                                        value,
                                                    ) =>
                                                        editForm.setData({
                                                            ...editForm.data,
                                                            subcategoryId:
                                                                value,
                                                            subSubcategoryId:
                                                                '',
                                                            category_id: '',
                                                        })
                                                    }
                                                    onSubSubcategoryChange={(
                                                        value,
                                                    ) =>
                                                        syncPickerToCategoryId(
                                                            editForm,
                                                            value,
                                                        )
                                                    }
                                                    onBrandChange={() => {}}
                                                    idPrefix={`edit-${brand.id}`}
                                                    showBrand={false}
                                                />
                                                <InputError
                                                    message={
                                                        editForm.errors
                                                            .category_id
                                                    }
                                                />
                                                <label className="flex items-center gap-2 text-sm">
                                                    <input
                                                        type="checkbox"
                                                        checked={
                                                            editForm.data
                                                                .is_active
                                                        }
                                                        onChange={(e) =>
                                                            editForm.setData(
                                                                'is_active',
                                                                e.target
                                                                    .checked,
                                                            )
                                                        }
                                                    />
                                                    Active
                                                </label>
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        disabled={
                                                            editForm.processing
                                                        }
                                                    >
                                                        Save
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            setEditingId(null)
                                                        }
                                                    >
                                                        Cancel
                                                    </Button>
                                                </div>
                                            </form>
                                        ) : (
                                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <p className="font-medium">
                                                        {brand.name}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {brand.category_path ??
                                                            'Unknown path'}{' '}
                                                        · {brand.products_count}{' '}
                                                        products
                                                        {!brand.is_active &&
                                                            ' · Inactive'}
                                                    </p>
                                                </div>
                                                <div className="flex gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            startEdit(brand)
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                    <Form
                                                        {...destroy.form(
                                                            brand.id,
                                                        )}
                                                        options={{
                                                            preserveScroll:
                                                                true,
                                                        }}
                                                    >
                                                        {() => (
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="ghost"
                                                                disabled={
                                                                    brand.products_count >
                                                                    0
                                                                }
                                                            >
                                                                Delete
                                                            </Button>
                                                        )}
                                                    </Form>
                                                </div>
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="rounded-2xl border border-border/80 bg-card/80 p-4">
                        <h2 className="mb-3 font-medium">Add brand</h2>
                        {subSubcategories.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Create a category tree first (for example
                                Fashion → Men&apos;s Fashion → Sneakers).
                            </p>
                        ) : (
                            <form
                                className="space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    createForm.post(store.url(), {
                                        preserveScroll: true,
                                        onSuccess: () => createForm.reset(),
                                    });
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={createForm.data.name}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={createForm.errors.name}
                                    />
                                </div>
                                <CatalogTaxonomyPicker
                                    taxonomy={taxonomy}
                                    categoryId={createForm.data.categoryId}
                                    subcategoryId={
                                        createForm.data.subcategoryId
                                    }
                                    subSubcategoryId={
                                        createForm.data.subSubcategoryId
                                    }
                                    brandId=""
                                    onCategoryChange={(value) =>
                                        createForm.setData({
                                            ...createForm.data,
                                            categoryId: value,
                                            subcategoryId: '',
                                            subSubcategoryId: '',
                                            category_id: '',
                                        })
                                    }
                                    onSubcategoryChange={(value) =>
                                        createForm.setData({
                                            ...createForm.data,
                                            subcategoryId: value,
                                            subSubcategoryId: '',
                                            category_id: '',
                                        })
                                    }
                                    onSubSubcategoryChange={(value) =>
                                        syncPickerToCategoryId(
                                            createForm,
                                            value,
                                        )
                                    }
                                    onBrandChange={() => {}}
                                    idPrefix="create-brand"
                                    showBrand={false}
                                />
                                <InputError
                                    message={createForm.errors.category_id}
                                />
                                <Button
                                    type="submit"
                                    disabled={createForm.processing}
                                    className="w-full"
                                >
                                    Create brand
                                </Button>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

BrandsIndex.layout = {
    breadcrumbs: [{ title: 'Brands', href: brandsIndex() }],
};
