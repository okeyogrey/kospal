import { Form, Head, useForm } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { EmptyState } from '@/components/states';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy,
    index as branchesIndex,
    store,
    update,
} from '@/routes/branches';
import { update as updateOperatingHours } from '@/routes/business/operating-hours';

type BranchRow = {
    id: number;
    name: string;
    code: string | null;
    city: string | null;
    phone: string | null;
    operating_mode: string | null;
    opens_at: string | null;
    closes_at: string | null;
    is_active: boolean;
};

type BusinessHours = {
    operating_mode: string;
    opens_at: string | null;
    closes_at: string | null;
};

type Limits = {
    max_branches: number;
    active_branches: number;
    can_add_branch: boolean;
    plan: string;
};

function BranchHoursEditor({ branch }: { branch: BranchRow }) {
    const form = useForm({
        name: branch.name,
        operating_mode: branch.operating_mode ?? '',
        opens_at: branch.opens_at ?? '',
        closes_at: branch.closes_at ?? '',
    });

    return (
        <form
            className="mt-3 grid gap-2 rounded-xl border border-border/60 p-3 sm:grid-cols-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(update.url(branch.id), { preserveScroll: true });
            }}
        >
            <div className="grid gap-1">
                <Label className="text-xs">Mode override</Label>
                <select
                    value={form.data.operating_mode}
                    onChange={(e) =>
                        form.setData('operating_mode', e.target.value)
                    }
                    className="h-9 rounded-md border border-input bg-transparent px-2 text-sm"
                >
                    <option value="">Inherit business</option>
                    <option value="always_open">24/7</option>
                    <option value="daytime">Daytime</option>
                </select>
            </div>
            <div className="grid gap-1">
                <Label className="text-xs">Opens</Label>
                <Input
                    type="time"
                    value={form.data.opens_at}
                    onChange={(e) => form.setData('opens_at', e.target.value)}
                />
            </div>
            <div className="grid gap-1">
                <Label className="text-xs">Closes</Label>
                <Input
                    type="time"
                    value={form.data.closes_at}
                    onChange={(e) => form.setData('closes_at', e.target.value)}
                />
            </div>
            <div className="flex items-end">
                <Button
                    type="submit"
                    size="sm"
                    variant="outline"
                    disabled={form.processing}
                    className="w-full"
                >
                    Save hours
                </Button>
            </div>
        </form>
    );
}

export default function BranchesIndex({
    branches,
    business_hours,
    limits,
}: {
    branches: BranchRow[];
    business_hours: BusinessHours;
    limits: Limits;
}) {
    const createForm = useForm({
        name: '',
        code: '',
        city: '',
        phone: '',
        is_active: true,
    });

    const hoursForm = useForm({
        operating_mode: business_hours.operating_mode,
        opens_at: business_hours.opens_at ?? '',
        closes_at: business_hours.closes_at ?? '',
    });

    return (
        <>
            <Head title="Branches" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Branches
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Plan {limits.plan}: {limits.active_branches}/
                            {limits.max_branches} active branches
                        </p>
                    </div>
                </div>

                <section className="rounded-2xl border border-border/80 bg-card/80 p-4">
                    <h2 className="mb-1 font-medium">Store operating hours</h2>
                    <p className="mb-3 text-sm text-muted-foreground">
                        Default for all branches. Use 24/7 for round-the-clock
                        stores, or daytime with open/close times.
                    </p>
                    <form
                        className="grid gap-3 sm:grid-cols-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            hoursForm.patch(updateOperatingHours.url(), {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <div className="grid gap-1">
                            <Label>Mode</Label>
                            <select
                                value={hoursForm.data.operating_mode}
                                onChange={(e) =>
                                    hoursForm.setData(
                                        'operating_mode',
                                        e.target.value,
                                    )
                                }
                                className="h-9 rounded-md border border-input bg-transparent px-2 text-sm"
                            >
                                <option value="always_open">24/7</option>
                                <option value="daytime">Daytime</option>
                            </select>
                        </div>
                        <div className="grid gap-1">
                            <Label>Opens</Label>
                            <Input
                                type="time"
                                value={hoursForm.data.opens_at}
                                onChange={(e) =>
                                    hoursForm.setData(
                                        'opens_at',
                                        e.target.value,
                                    )
                                }
                                disabled={
                                    hoursForm.data.operating_mode !== 'daytime'
                                }
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label>Closes</Label>
                            <Input
                                type="time"
                                value={hoursForm.data.closes_at}
                                onChange={(e) =>
                                    hoursForm.setData(
                                        'closes_at',
                                        e.target.value,
                                    )
                                }
                                disabled={
                                    hoursForm.data.operating_mode !== 'daytime'
                                }
                            />
                        </div>
                        <div className="flex items-end">
                            <Button
                                type="submit"
                                disabled={hoursForm.processing}
                                className="w-full"
                            >
                                Save store hours
                            </Button>
                        </div>
                    </form>
                </section>

                <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                    <div className="rounded-2xl border border-border/80 bg-card/80">
                        {branches.length === 0 ? (
                            <EmptyState
                                title="No branches yet"
                                description="Create your first branch to continue."
                                icon={<Building2 className="size-5" />}
                            />
                        ) : (
                            <ul className="divide-y divide-border/70">
                                {branches.map((branch) => (
                                    <li key={branch.id} className="p-4">
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium">
                                                        {branch.name}
                                                    </p>
                                                    <Badge
                                                        variant={
                                                            branch.is_active
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {branch.is_active
                                                            ? 'Active'
                                                            : 'Inactive'}
                                                    </Badge>
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    {[
                                                        branch.code,
                                                        branch.city,
                                                        branch.phone,
                                                        branch.operating_mode ===
                                                        'always_open'
                                                            ? '24/7'
                                                            : branch.operating_mode ===
                                                                'daytime'
                                                              ? `Daytime ${branch.opens_at ?? '?'}–${branch.closes_at ?? '?'}`
                                                              : 'Hours: inherit',
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                <Form
                                                    {...update.form(branch.id)}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    {() => (
                                                        <>
                                                            <input
                                                                type="hidden"
                                                                name="is_active"
                                                                value={
                                                                    branch.is_active
                                                                        ? 0
                                                                        : 1
                                                                }
                                                            />
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="outline"
                                                            >
                                                                {branch.is_active
                                                                    ? 'Deactivate'
                                                                    : 'Activate'}
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                                <Form
                                                    {...destroy.form(
                                                        branch.id,
                                                    )}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    {() => (
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="ghost"
                                                        >
                                                            Delete
                                                        </Button>
                                                    )}
                                                </Form>
                                            </div>
                                        </div>
                                        <BranchHoursEditor branch={branch} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="rounded-2xl border border-border/80 bg-card/80 p-4">
                        <h2 className="mb-3 font-medium">Add branch</h2>
                        {!limits.can_add_branch ? (
                            <p className="text-sm text-muted-foreground">
                                Active branch limit reached for the{' '}
                                {limits.plan} plan. Deactivate a branch or
                                request an upgrade.
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
                                <div className="grid gap-2">
                                    <Label htmlFor="code">Code</Label>
                                    <Input
                                        id="code"
                                        value={createForm.data.code}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'code',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="city">City</Label>
                                    <Input
                                        id="city"
                                        value={createForm.data.city}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'city',
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
                                    Create branch
                                </Button>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

BranchesIndex.layout = {
    breadcrumbs: [{ title: 'Branches', href: branchesIndex() }],
};
