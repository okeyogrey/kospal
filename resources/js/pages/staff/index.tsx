import { Form, Head, useForm, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { EmptyState } from '@/components/states';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy as destroyInvitation,
    store as storeInvitation,
} from '@/routes/staff/invitations';
import {
    destroy as destroyMembership,
    update as updateMembership,
} from '@/routes/staff/memberships';
import { update as updateSettings } from '@/routes/staff/settings';
import { index as staffIndex } from '@/routes/staff';

type Membership = {
    id: number;
    role: string;
    negotiation_floor_percent: number;
    is_active: boolean;
    user: { id: number; name: string; email: string };
    branch_ids: number[];
};

type Invitation = {
    id: number;
    email: string;
    role: string;
    expires_at: string;
    branch_ids: number[] | null;
};

type BranchOption = { id: number; name: string };

type Limits = {
    max_staff: number | null;
    staff_seats: number;
    can_add_staff: boolean;
    plan: string;
};

type StaffSettings = {
    cashiers_can_log_expenses: boolean;
    can_edit: boolean;
};

function MembershipEditor({
    membership,
    branches,
    assignableRoles,
}: {
    membership: Membership;
    branches: BranchOption[];
    assignableRoles: string[];
}) {
    const form = useForm({
        role: membership.role,
        branch_ids: membership.branch_ids,
        negotiation_floor_percent: membership.negotiation_floor_percent ?? 100,
    });

    const toggleBranch = (branchId: number) => {
        const current = form.data.branch_ids;
        form.setData(
            'branch_ids',
            current.includes(branchId)
                ? current.filter((id) => id !== branchId)
                : [...current, branchId],
        );
    };

    return (
        <form
            className="flex flex-wrap items-end gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(updateMembership.url(membership.id), {
                    preserveScroll: true,
                });
            }}
        >
            <div className="grid gap-1">
                <select
                    name="role"
                    value={form.data.role}
                    onChange={(event) =>
                        form.setData('role', event.target.value)
                    }
                    className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                >
                    {assignableRoles.map((role) => (
                        <option key={role} value={role}>
                            {role}
                        </option>
                    ))}
                </select>
                <InputError message={form.errors.role} />
            </div>
            <div className="grid gap-1">
                <div className="flex flex-wrap gap-2">
                    {branches.map((branch) => (
                        <label
                            key={branch.id}
                            className="flex items-center gap-1 text-xs"
                        >
                            <input
                                type="checkbox"
                                checked={form.data.branch_ids.includes(
                                    branch.id,
                                )}
                                onChange={() => toggleBranch(branch.id)}
                            />
                            {branch.name}
                        </label>
                    ))}
                </div>
                {form.data.role === 'manager' ? (
                    <p className="text-xs text-muted-foreground">
                        Leave unchecked for access to all branches.
                    </p>
                ) : null}
                <InputError message={form.errors.branch_ids} />
            </div>
            {form.data.role === 'cashier' ? (
                <div className="grid gap-1">
                    <Label className="text-xs">
                        Price floor % (without PIN)
                    </Label>
                    <Input
                        type="number"
                        min={0}
                        max={100}
                        value={form.data.negotiation_floor_percent}
                        onChange={(event) =>
                            form.setData(
                                'negotiation_floor_percent',
                                Number(event.target.value || 100),
                            )
                        }
                        className="h-9 w-24"
                    />
                    <InputError
                        message={form.errors.negotiation_floor_percent}
                    />
                </div>
            ) : null}
            <Button
                type="submit"
                size="sm"
                variant="outline"
                disabled={form.processing}
            >
                {form.processing ? 'Saving…' : 'Save'}
            </Button>
        </form>
    );
}

function StaffSettingsCard({ settings }: { settings: StaffSettings }) {
    const form = useForm({
        cashiers_can_log_expenses: settings.cashiers_can_log_expenses,
    });

    return (
        <section className="rounded-2xl border border-border/80 bg-card/80 p-4">
            <h2 className="mb-3 font-medium">Staff settings</h2>
            <form
                className="space-y-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.patch(updateSettings.url(), {
                        preserveScroll: true,
                    });
                }}
            >
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.cashiers_can_log_expenses}
                        onChange={(event) =>
                            form.setData(
                                'cashiers_can_log_expenses',
                                event.target.checked,
                            )
                        }
                    />
                    Allow cashiers to log expenses
                </label>
                <InputError
                    message={form.errors.cashiers_can_log_expenses}
                />
                <Button
                    type="submit"
                    size="sm"
                    variant="outline"
                    disabled={form.processing}
                >
                    Save settings
                </Button>
            </form>
        </section>
    );
}

export default function StaffIndex({
    memberships,
    invitations,
    branches,
    assignableRoles,
    limits,
    settings,
}: {
    memberships: Membership[];
    invitations: Invitation[];
    branches: BranchOption[];
    assignableRoles: string[];
    limits: Limits;
    settings: StaffSettings;
}) {
    const flash = usePage().props.flash as
        | { success?: string | null; error?: string | null }
        | undefined;

    const inviteForm = useForm({
        email: '',
        role: assignableRoles[0] ?? 'cashier',
        branch_ids: [] as number[],
    });

    const toggleBranch = (branchId: number) => {
        const current = inviteForm.data.branch_ids;
        inviteForm.setData(
            'branch_ids',
            current.includes(branchId)
                ? current.filter((id) => id !== branchId)
                : [...current, branchId],
        );
    };

    return (
        <>
            <Head title="Staff" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold">Staff</h1>
                    <p className="text-sm text-muted-foreground">
                        Plan {limits.plan}: {limits.staff_seats}
                        {limits.max_staff === null
                            ? ' seats in use'
                            : `/${limits.max_staff} seats`}
                    </p>
                </div>

                {flash?.success ? (
                    <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200">
                        {flash.success}
                    </div>
                ) : null}

                {flash?.error ? (
                    <div className="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        {flash.error}
                    </div>
                ) : null}

                {settings.can_edit ? (
                    <StaffSettingsCard settings={settings} />
                ) : null}

                <div className="grid gap-6 xl:grid-cols-[1fr_22rem]">
                    <div className="space-y-6">
                        <section className="rounded-2xl border border-border/80 bg-card/80">
                            <div className="border-b border-border/70 px-4 py-3 font-medium">
                                Team members
                            </div>
                            {memberships.length === 0 ? (
                                <EmptyState
                                    title="No staff yet"
                                    icon={<Users className="size-5" />}
                                />
                            ) : (
                                <ul className="divide-y divide-border/70">
                                    {memberships.map((membership) => (
                                        <li
                                            key={membership.id}
                                            className="flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {membership.user.name}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {membership.user.email}
                                                </p>
                                                <div className="mt-1 flex gap-2">
                                                    <Badge>
                                                        {membership.role}
                                                    </Badge>
                                                    {!membership.is_active && (
                                                        <Badge variant="secondary">
                                                            Inactive
                                                        </Badge>
                                                    )}
                                                </div>
                                            </div>
                                            {membership.role !== 'owner' &&
                                                membership.is_active && (
                                                    <div className="flex flex-wrap gap-2">
                                                        <MembershipEditor
                                                            membership={
                                                                membership
                                                            }
                                                            branches={branches}
                                                            assignableRoles={
                                                                assignableRoles
                                                            }
                                                        />
                                                        <Form
                                                            {...destroyMembership.form(
                                                                membership.id,
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
                                                                >
                                                                    Deactivate
                                                                </Button>
                                                            )}
                                                        </Form>
                                                    </div>
                                                )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section className="rounded-2xl border border-border/80 bg-card/80">
                            <div className="border-b border-border/70 px-4 py-3 font-medium">
                                Pending invitations
                            </div>
                            {invitations.length === 0 ? (
                                <p className="p-4 text-sm text-muted-foreground">
                                    No pending invitations.
                                </p>
                            ) : (
                                <ul className="divide-y divide-border/70">
                                    {invitations.map((invitation) => (
                                        <li
                                            key={invitation.id}
                                            className="flex items-center justify-between gap-3 p-4"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {invitation.email}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {invitation.role}
                                                </p>
                                            </div>
                                            <Form
                                                {...destroyInvitation.form(
                                                    invitation.id,
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
                                                        Revoke
                                                    </Button>
                                                )}
                                            </Form>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>

                    <aside className="rounded-2xl border border-border/80 bg-card/80 p-4">
                        <h2 className="mb-3 font-medium">Invite staff</h2>
                        {!limits.can_add_staff ? (
                            <p className="text-sm text-muted-foreground">
                                Staff seat limit reached for the {limits.plan}{' '}
                                plan.
                            </p>
                        ) : (
                            <form
                                className="space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    inviteForm.post(storeInvitation.url(), {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            inviteForm.reset(
                                                'email',
                                                'branch_ids',
                                            ),
                                    });
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={inviteForm.data.email}
                                        onChange={(e) =>
                                            inviteForm.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={inviteForm.errors.email}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="role">Role</Label>
                                    <select
                                        id="role"
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                        value={inviteForm.data.role}
                                        onChange={(e) =>
                                            inviteForm.setData(
                                                'role',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        {assignableRoles.map((role) => (
                                            <option key={role} value={role}>
                                                {role}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={inviteForm.errors.role}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Branch access</Label>
                                    <div className="space-y-1">
                                        {branches.map((branch) => (
                                            <label
                                                key={branch.id}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={inviteForm.data.branch_ids.includes(
                                                        branch.id,
                                                    )}
                                                    onChange={() =>
                                                        toggleBranch(branch.id)
                                                    }
                                                />
                                                {branch.name}
                                            </label>
                                        ))}
                                    </div>
                                    {inviteForm.data.role === 'manager' ? (
                                        <p className="text-xs text-muted-foreground">
                                            Optional for managers — leave
                                            unchecked for all branches.
                                        </p>
                                    ) : null}
                                    <InputError
                                        message={inviteForm.errors.branch_ids}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={inviteForm.processing}
                                >
                                    Send invitation
                                </Button>
                            </form>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

StaffIndex.layout = {
    breadcrumbs: [{ title: 'Staff', href: staffIndex() }],
};
