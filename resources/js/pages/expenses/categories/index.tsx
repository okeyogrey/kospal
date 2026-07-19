import { Form, Head, Link, router, useForm } from '@inertiajs/react';
import { Tags } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { EmptyState } from '@/components/states';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import {
    destroy,
    index as categoriesIndex,
    store,
    update,
} from '@/routes/expense-categories';
import { index as expensesIndex } from '@/routes/expenses';

type CategoryRow = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    expenses_count: number;
};

export default function ExpenseCategoriesIndex({
    categories,
    filters,
}: {
    categories: CategoryRow[];
    filters: { search: string };
}) {
    const { t } = useTranslations();
    const [editingId, setEditingId] = useState<number | null>(null);
    const createForm = useForm({
        name: '',
        description: '',
        is_active: true,
    });
    const editForm = useForm({
        name: '',
        description: '',
        is_active: true,
    });

    const startEdit = (category: CategoryRow) => {
        setEditingId(category.id);
        editForm.setData({
            name: category.name,
            description: category.description ?? '',
            is_active: category.is_active,
        });
        editForm.clearErrors();
    };

    return (
        <>
            <Head title={t('pages.expense_categories.title')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            {t('pages.expense_categories.title')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('pages.expense_categories.description')}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        asChild
                        className="w-full sm:w-auto"
                    >
                        <Link href={expensesIndex()}>
                            {t('pages.expenses.back')}
                        </Link>
                    </Button>
                </div>

                <form
                    className="flex gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.get(
                            categoriesIndex.url({
                                query: {
                                    search: String(data.get('search') ?? ''),
                                },
                            }),
                            {},
                            { preserveState: true },
                        );
                    }}
                >
                    <Input
                        name="search"
                        placeholder={t(
                            'pages.expense_categories.search_placeholder',
                        )}
                        defaultValue={filters.search}
                        className="max-w-sm"
                    />
                    <Button type="submit" variant="outline">
                        {t('pages.expenses.filter')}
                    </Button>
                </form>

                <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                    <div className="rounded-2xl border border-border/80 bg-card/80">
                        {categories.length === 0 ? (
                            <EmptyState
                                title={t('pages.expense_categories.empty_title')}
                                description={t(
                                    'pages.expense_categories.empty_description',
                                )}
                                icon={<Tags className="size-5" />}
                            />
                        ) : (
                            <ul className="divide-y divide-border/70">
                                {categories.map((category) => (
                                    <li key={category.id} className="p-4">
                                        {editingId === category.id ? (
                                            <form
                                                className="grid gap-3"
                                                onSubmit={(event) => {
                                                    event.preventDefault();
                                                    editForm.patch(
                                                        update.url(category.id),
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
                                                <div className="grid gap-3 sm:grid-cols-2">
                                                    <div className="grid gap-1">
                                                        <Input
                                                            value={
                                                                editForm.data
                                                                    .name
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
                                                        placeholder={t(
                                                            'pages.expense_categories.description_field',
                                                        )}
                                                    />
                                                </div>
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
                                                    {t(
                                                        'pages.expense_categories.active',
                                                    )}
                                                </label>
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        disabled={
                                                            editForm.processing
                                                        }
                                                    >
                                                        {t(
                                                            'pages.expenses.save',
                                                        )}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            setEditingId(null)
                                                        }
                                                    >
                                                        {t(
                                                            'pages.expenses.cancel',
                                                        )}
                                                    </Button>
                                                </div>
                                            </form>
                                        ) : (
                                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                <div className="space-y-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium">
                                                            {category.name}
                                                        </span>
                                                        <Badge
                                                            variant={
                                                                category.is_active
                                                                    ? 'secondary'
                                                                    : 'outline'
                                                            }
                                                        >
                                                            {category.is_active
                                                                ? t(
                                                                      'pages.expense_categories.active',
                                                                  )
                                                                : t(
                                                                      'pages.expense_categories.inactive',
                                                                  )}
                                                        </Badge>
                                                    </div>
                                                    {category.description ? (
                                                        <p className="text-sm text-muted-foreground">
                                                            {
                                                                category.description
                                                            }
                                                        </p>
                                                    ) : null}
                                                    <p className="text-xs text-muted-foreground">
                                                        {category.expenses_count}{' '}
                                                        {t(
                                                            'pages.expense_categories.expenses_count',
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            startEdit(category)
                                                        }
                                                    >
                                                        {t(
                                                            'pages.expenses.edit',
                                                        )}
                                                    </Button>
                                                    <Form
                                                        {...destroy.form(
                                                            category.id,
                                                        )}
                                                        onSubmit={(event) => {
                                                            if (
                                                                !window.confirm(
                                                                    t(
                                                                        'pages.expense_categories.confirm_delete',
                                                                    ),
                                                                )
                                                            ) {
                                                                event.preventDefault();
                                                            }
                                                        }}
                                                    >
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="destructive"
                                                            disabled={
                                                                category.expenses_count >
                                                                0
                                                            }
                                                        >
                                                            {t(
                                                                'pages.expenses.delete',
                                                            )}
                                                        </Button>
                                                    </Form>
                                                </div>
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="h-fit space-y-4 rounded-2xl border border-border/80 bg-card/80 p-4">
                        <div>
                            <h2 className="font-medium">
                                {t('pages.expense_categories.add')}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {t('pages.expense_categories.add_hint')}
                            </p>
                        </div>
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
                            <div className="space-y-2">
                                <Label htmlFor="name">
                                    {t('pages.expense_categories.name')}
                                </Label>
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
                            <div className="space-y-2">
                                <Label htmlFor="description">
                                    {t(
                                        'pages.expense_categories.description_field',
                                    )}
                                </Label>
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
                                className="w-full"
                                disabled={createForm.processing}
                            >
                                {createForm.processing
                                    ? t('pages.expenses.saving')
                                    : t('pages.expense_categories.add')}
                            </Button>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}

ExpenseCategoriesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Expenses',
            href: expensesIndex(),
        },
        {
            title: 'Categories',
            href: categoriesIndex(),
        },
    ],
};
