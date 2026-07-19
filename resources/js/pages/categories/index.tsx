import { Form, Head, useForm } from '@inertiajs/react';
import { Tags } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { EmptyState } from '@/components/states';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy,
    index as categoriesIndex,
    store,
    update,
} from '@/routes/categories';

type CategoryRow = {
    id: number;
    name: string;
    description: string | null;
    products_count: number;
};

export default function CategoriesIndex({
    categories,
}: {
    categories: CategoryRow[];
}) {
    const [editingId, setEditingId] = useState<number | null>(null);

    const createForm = useForm({
        name: '',
        description: '',
    });
    const editForm = useForm({
        name: '',
        description: '',
    });

    const startEdit = (category: CategoryRow) => {
        setEditingId(category.id);
        editForm.setData({
            name: category.name,
            description: category.description ?? '',
        });
        editForm.clearErrors();
    };

    const canDelete = (category: CategoryRow) => category.products_count === 0;

    return (
        <>
            <Head title="Categories" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold">
                        Categories
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Organize products into categories for your catalog.
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                    <div className="rounded-2xl border border-border/80 bg-card/80">
                        {categories.length === 0 ? (
                            <EmptyState
                                title="No categories yet"
                                description="Create a category such as Fashion or Electronics."
                                icon={<Tags className="size-5" />}
                            />
                        ) : (
                            <ul className="divide-y divide-border/70">
                                {categories.map((category) => (
                                    <li key={category.id} className="p-4">
                                        {editingId === category.id ? (
                                            <form
                                                className="grid gap-3 sm:grid-cols-[1fr_1fr_auto]"
                                                onSubmit={(event) => {
                                                    event.preventDefault();
                                                    editForm.patch(
                                                        update.url(
                                                            category.id,
                                                        ),
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
                                                <div className="grid gap-1">
                                                    <Input
                                                        value={
                                                            editForm.data.name
                                                        }
                                                        onChange={(e) =>
                                                            editForm.setData(
                                                                'name',
                                                                e.target
                                                                    .value,
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
                                                <Input
                                                    value={
                                                        editForm.data
                                                            .description
                                                    }
                                                    onChange={(e) =>
                                                        editForm.setData(
                                                            'description',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Description"
                                                />
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
                                                        {category.name}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {category.description ||
                                                            'No description'}{' '}
                                                        · {category.products_count}{' '}
                                                        products
                                                    </p>
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            startEdit(category)
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                    <Form
                                                        {...destroy.form(
                                                            category.id,
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
                                                                    !canDelete(
                                                                        category,
                                                                    )
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
                        <h2 className="mb-3 font-medium">Add category</h2>
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
                                <InputError message={createForm.errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <Input
                                    id="description"
                                    value={createForm.data.description}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                                className="w-full"
                            >
                                Create
                            </Button>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categories', href: categoriesIndex() }],
};
