import { router, usePage } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { clockIn, clockOut } from '@/routes/shifts';

function formatSince(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

export function ShiftClockWidget() {
    const { workspace, flash } = usePage().props;
    const [processing, setProcessing] = useState(false);

    if (!workspace.shift_required) {
        return null;
    }

    const shift = workspace.active_shift;
    const onDuty = Boolean(shift?.on_active_branch);

    const submit = (url: string) => {
        setProcessing(true);
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="flex max-w-[18rem] flex-col items-end gap-1">
            {flash?.error ? (
                <p className="max-w-full truncate text-[11px] text-destructive">
                    {flash.error}
                </p>
            ) : null}
            {workspace.outside_hours && !onDuty ? (
                <p className="text-[11px] text-muted-foreground">
                    Outside daytime hours
                </p>
            ) : null}
            {onDuty ? (
                <div className="flex items-center gap-2">
                    <span className="hidden text-xs text-muted-foreground sm:inline">
                        On since {formatSince(shift?.clocked_in_at ?? null)}
                    </span>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="h-9 gap-1.5"
                        disabled={processing}
                        onClick={() => submit(clockOut.url())}
                    >
                        <Clock className="size-3.5" />
                        Clock out
                    </Button>
                </div>
            ) : (
                <div className="flex items-center gap-2">
                    {shift && !shift.on_active_branch ? (
                        <span className="hidden text-[11px] text-amber-700 sm:inline dark:text-amber-300">
                            Open at {shift.branch_name} — clock out first
                        </span>
                    ) : null}
                    <Button
                        type="button"
                        size="sm"
                        className="h-9 gap-1.5"
                        disabled={
                            processing ||
                            Boolean(shift && !shift.on_active_branch)
                        }
                        onClick={() => submit(clockIn.url())}
                    >
                        <Clock className="size-3.5" />
                        Clock in
                    </Button>
                </div>
            )}
        </div>
    );
}
