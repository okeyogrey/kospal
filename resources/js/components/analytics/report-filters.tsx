import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type BranchOption = { id: number; name: string };

type Filters = {
    branch_id: number | null;
    date_from: string;
    date_to: string;
    grain?: string;
    include_voided?: boolean;
};

export function AnalyticsFilters({
    actionUrl,
    branches,
    filters,
    showGrain = false,
    showIncludeVoided = false,
    submitLabel = 'Apply',
}: {
    actionUrl: string;
    branches: BranchOption[];
    filters: Filters;
    showGrain?: boolean;
    showIncludeVoided?: boolean;
    submitLabel?: string;
}) {
    return (
        <form
            className="grid gap-3 rounded-2xl border border-border/80 bg-card/60 p-4 sm:grid-cols-2 lg:grid-cols-6"
            onSubmit={(event) => {
                event.preventDefault();
                const data = new FormData(event.currentTarget);
                const query: Record<string, string> = {};

                for (const [key, value] of data.entries()) {
                    const text = String(value);
                    if (text !== '') {
                        query[key] = text;
                    }
                }

                router.get(actionUrl, query, {
                    preserveState: true,
                    preserveScroll: true,
                });
            }}
        >
            <div className="space-y-1.5">
                <Label htmlFor="branch_id">Branch</Label>
                <select
                    id="branch_id"
                    name="branch_id"
                    defaultValue={filters.branch_id ?? ''}
                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                >
                    <option value="">All branches</option>
                    {branches.map((branch) => (
                        <option key={branch.id} value={branch.id}>
                            {branch.name}
                        </option>
                    ))}
                </select>
            </div>

            <div className="space-y-1.5">
                <Label htmlFor="date_from">From</Label>
                <Input
                    id="date_from"
                    name="date_from"
                    type="date"
                    defaultValue={filters.date_from}
                />
            </div>

            <div className="space-y-1.5">
                <Label htmlFor="date_to">To</Label>
                <Input
                    id="date_to"
                    name="date_to"
                    type="date"
                    defaultValue={filters.date_to}
                />
            </div>

            {showGrain ? (
                <div className="space-y-1.5">
                    <Label htmlFor="grain">Group by</Label>
                    <select
                        id="grain"
                        name="grain"
                        defaultValue={filters.grain ?? 'day'}
                        className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="day">Day</option>
                        <option value="week">Week</option>
                        <option value="month">Month</option>
                    </select>
                </div>
            ) : null}

            {showIncludeVoided ? (
                <div className="flex items-end gap-2 pb-1">
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            name="include_voided"
                            value="1"
                            defaultChecked={Boolean(filters.include_voided)}
                            className="size-4 rounded border"
                        />
                        Include voided
                    </label>
                </div>
            ) : null}

            <div className="flex items-end">
                <Button type="submit" className="w-full">
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
