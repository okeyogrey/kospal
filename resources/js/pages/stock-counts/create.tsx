import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Option = { id: number; name: string };

export default function StockCountsCreate({
    branches,
    defaultBranchId,
}: {
    branches: Option[];
    defaultBranchId: number | null;
}) {
    const form = useForm({
        branch_id: String(defaultBranchId ?? branches[0]?.id ?? ''),
        notes: '',
    });

    return (
        <>
            <Head title="Start stock count" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="font-display text-2xl font-semibold">
                            Start stock count
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Creates a draft covering every active product at
                            the branch. Counted quantities are entered next.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        asChild
                        className="w-full sm:w-auto"
                    >
                        <Link href="/stock-counts">Back to stock counts</Link>
                    </Button>
                </div>

                <form
                    className="mx-auto w-full max-w-lg space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            branch_id: Number(data.branch_id),
                        }));
                        form.post('/stock-counts');
                    }}
                >
                    <div className="space-y-2">
                        <Label htmlFor="branch_id">Branch</Label>
                        <select
                            id="branch_id"
                            value={form.data.branch_id}
                            onChange={(event) =>
                                form.setData('branch_id', event.target.value)
                            }
                            className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="">Select branch</option>
                            {branches.map((branch) => (
                                <option key={branch.id} value={branch.id}>
                                    {branch.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.branch_id} />
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
                            placeholder="Optional notes about this count"
                        />
                        <InputError message={form.errors.notes} />
                    </div>

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            asChild
                            className="w-full sm:w-auto"
                        >
                            <Link href="/stock-counts">Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="w-full sm:w-auto"
                        >
                            Start count
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
