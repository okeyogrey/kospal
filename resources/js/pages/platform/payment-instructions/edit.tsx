import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Instructions = {
    title: string;
    body: string;
    bank_name: string | null;
    account_name: string | null;
    account_number: string | null;
    mobile_money: string | null;
    support_note: string | null;
};

export default function PaymentInstructionsEdit({
    instructions,
}: {
    instructions: Instructions;
}) {
    const form = useForm({
        title: instructions.title,
        body: instructions.body,
        bank_name: instructions.bank_name ?? '',
        account_name: instructions.account_name ?? '',
        account_number: instructions.account_number ?? '',
        mobile_money: instructions.mobile_money ?? '',
        support_note: instructions.support_note ?? '',
    });

    return (
        <>
            <Head title="Payment instructions" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold">
                        Payment instructions
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Business owners see these details before submitting a
                        transaction code.
                    </p>
                </div>

                <form
                    className="max-w-2xl space-y-4 rounded-2xl border border-border/80 bg-card/80 p-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put('/platform/payment-instructions', {
                            preserveScroll: true,
                        });
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="title">Title</Label>
                        <input
                            id="title"
                            className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="body">Instructions</Label>
                        <textarea
                            id="body"
                            className="border-input bg-background min-h-28 w-full rounded-md border px-3 py-2 text-sm"
                            value={form.data.body}
                            onChange={(e) =>
                                form.setData('body', e.target.value)
                            }
                        />
                        <InputError message={form.errors.body} />
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="bank_name">Bank name</Label>
                            <input
                                id="bank_name"
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={form.data.bank_name}
                                onChange={(e) =>
                                    form.setData('bank_name', e.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="account_name">Account name</Label>
                            <input
                                id="account_name"
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={form.data.account_name}
                                onChange={(e) =>
                                    form.setData(
                                        'account_name',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="account_number">
                                Account number
                            </Label>
                            <input
                                id="account_number"
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={form.data.account_number}
                                onChange={(e) =>
                                    form.setData(
                                        'account_number',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="mobile_money">Mobile money</Label>
                            <input
                                id="mobile_money"
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={form.data.mobile_money}
                                onChange={(e) =>
                                    form.setData(
                                        'mobile_money',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="support_note">Support note</Label>
                        <textarea
                            id="support_note"
                            className="border-input bg-background min-h-20 w-full rounded-md border px-3 py-2 text-sm"
                            value={form.data.support_note}
                            onChange={(e) =>
                                form.setData('support_note', e.target.value)
                            }
                        />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        Save instructions
                    </Button>
                </form>
            </div>
        </>
    );
}

PaymentInstructionsEdit.layout = {
    breadcrumbs: [
        {
            title: 'Payment instructions',
            href: '/platform/payment-instructions',
        },
    ],
};
