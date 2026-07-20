import { router, usePage } from '@inertiajs/react';
import { Banknote } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { toMinor } from '@/lib/money';
import { open as openCashSession } from '@/routes/cash-sessions';

export function CashDrawerWidget() {
    const { workspace, flash } = usePage().props;
    const currency = workspace.business?.currency ?? 'KES';
    const [processing, setProcessing] = useState(false);
    const [openDialog, setOpenDialog] = useState(false);
    const [openingFloat, setOpeningFloat] = useState('');

    if (!workspace.shift_required) {
        return null;
    }

    const shift = workspace.active_shift;
    const session = workspace.active_cash_session;
    const onDuty = Boolean(shift?.on_active_branch);

    if (!onDuty) {
        return null;
    }

    if (session) {
        return (
            <Button
                type="button"
                size="sm"
                variant="outline"
                className="h-9 gap-1.5"
                asChild
            >
                <a href={`/cash-sessions/${session.id}`}>
                    <Banknote className="size-3.5" />
                    Drawer open
                </a>
            </Button>
        );
    }

    const submitOpen = () => {
        setProcessing(true);
        router.post(
            openCashSession.url(),
            {
                opening_float: toMinor(openingFloat || '0', currency),
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => setOpenDialog(false),
            },
        );
    };

    return (
        <>
            <div className="flex max-w-[18rem] flex-col items-end gap-1">
                {flash?.error ? (
                    <p className="max-w-full truncate text-[11px] text-destructive">
                        {flash.error}
                    </p>
                ) : null}
                <Button
                    type="button"
                    size="sm"
                    className="h-9 gap-1.5"
                    disabled={processing}
                    onClick={() => setOpenDialog(true)}
                >
                    <Banknote className="size-3.5" />
                    Open drawer
                </Button>
            </div>

            <Dialog open={openDialog} onOpenChange={setOpenDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Opening float</DialogTitle>
                        <DialogDescription>
                            Count the cash in the drawer before starting sales.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="opening_float">Amount</Label>
                        <Input
                            id="opening_float"
                            type="number"
                            min="0"
                            step="0.01"
                            value={openingFloat}
                            onChange={(e) => setOpeningFloat(e.target.value)}
                            placeholder="0.00"
                            autoFocus
                        />
                        <InputError message={undefined} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpenDialog(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={processing}
                            onClick={submitOpen}
                        >
                            Open drawer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
