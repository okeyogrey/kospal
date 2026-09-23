import { Head, useForm, usePage } from '@inertiajs/react';
import { Copy, Gift, Mail, MessageCircle } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';

type ReferralRow = {
    id: number;
    status: string;
    business_name: string | null;
    onboarded_at: string | null;
    qualifies_at: string | null;
    qualified_at: string | null;
};

type Share = {
    code: string;
    url: string;
    expires_at: string;
    inviter_name: string;
    whatsapp_url: string;
    message: string;
};

type Props = {
    connected_to_server: boolean;
    server_required: boolean;
    available_percent: number;
    applied_percent_cap: number;
    credit_percent: number;
    referred_by?: {
        business_name: string | null;
        status: string;
        qualifies_at: string | null;
    } | null;
    active_code: { code: string; expires_at: string; url: string } | null;
    share: Share | null;
    referrals: ReferralRow[];
    stats: { pending: number; qualified: number; voided: number };
};

export default function ReferralsIndex({
    available_percent,
    applied_percent_cap,
    credit_percent,
    referred_by,
    share,
    referrals,
    stats,
    connected_to_server,
    server_required,
}: Props) {
    const flash = usePage().props.flash as
        | { success?: string; error?: string }
        | undefined;
    const [, copyText] = useClipboard();
    const [copied, setCopied] = useState<string | null>(null);
    const generateForm = useForm({});
    const emailForm = useForm({ email: '' });

    const copy = async (value: string, key: string) => {
        await copyText(value);
        setCopied(key);
    };

    return (
        <>
            <Head title="Referrals" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold">
                        Referrals
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Share KOSPAL with other shop owners. After they finish
                        setup and stay active for a week, you both get{' '}
                        {credit_percent}% off the first payment after the trial.
                        Credits stack — 10 successful invites cover that
                        payment.
                    </p>
                </div>

                {flash?.success ? (
                    <Alert>
                        <AlertTitle>Invite ready</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}
                {flash?.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Could not send invite</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                {connected_to_server ? (
                    <p className="text-xs text-muted-foreground">
                        Invite tracking is synced with the central KOSPAL
                        server.
                    </p>
                ) : null}

                {server_required ? (
                    <Alert variant="destructive">
                        <AlertTitle>Connect this install</AlertTitle>
                        <AlertDescription>
                            Desktop shops need KOSPAL_REFERRAL_SERVER_URL so
                            invites can be shared with other shops. Local codes
                            only work on this machine.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <section className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-2xl border border-border/80 bg-card/80 p-4">
                        <p className="text-sm text-muted-foreground">
                            Credit for next payment
                        </p>
                        <p className="font-display text-2xl font-semibold">
                            {applied_percent_cap}%
                        </p>
                        {available_percent > 100 ? (
                            <p className="text-xs text-muted-foreground">
                                {available_percent - 100}% carries to later
                                payments
                            </p>
                        ) : null}
                    </div>
                    <div className="rounded-2xl border border-border/80 bg-card/80 p-4">
                        <p className="text-sm text-muted-foreground">
                            Waiting to qualify
                        </p>
                        <p className="font-display text-2xl font-semibold">
                            {stats.pending}
                        </p>
                    </div>
                    <div className="rounded-2xl border border-border/80 bg-card/80 p-4">
                        <p className="text-sm text-muted-foreground">
                            Qualified invites
                        </p>
                        <p className="font-display text-2xl font-semibold">
                            {stats.qualified}
                        </p>
                    </div>
                </section>

                {referred_by ? (
                    <Alert>
                        <Gift className="size-4" />
                        <AlertTitle>You joined with an invite</AlertTitle>
                        <AlertDescription>
                            {referred_by.business_name ?? 'Another shop'}{' '}
                            referred you. Status: {referred_by.status}
                            {referred_by.qualifies_at
                                ? ` · qualifies ${referred_by.qualifies_at}`
                                : ''}
                            .
                        </AlertDescription>
                    </Alert>
                ) : null}

                <section className="rounded-2xl border border-border/80 bg-card/80 p-5">
                    <h2 className="mb-3 font-medium">Share your invite</h2>
                    {share ? (
                        <div className="space-y-4">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Code
                                    </p>
                                    <p className="font-display text-xl font-semibold tracking-wide">
                                        {share.code}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Expires
                                    </p>
                                    <p className="text-sm">{share.expires_at}</p>
                                </div>
                            </div>
                            <p className="break-all text-sm">{share.url}</p>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => copy(share.url, 'url')}
                                >
                                    <Copy className="size-4" />
                                    {copied === 'url' ? 'Copied link' : 'Copy link'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => copy(share.code, 'code')}
                                >
                                    <Copy className="size-4" />
                                    {copied === 'code' ? 'Copied code' : 'Copy code'}
                                </Button>
                                <Button type="button" variant="outline" asChild>
                                    <a href={share.whatsapp_url} target="_blank" rel="noreferrer">
                                        <MessageCircle className="size-4" />
                                        WhatsApp
                                    </a>
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <p className="mb-4 text-sm text-muted-foreground">
                            Generate a link you can share. It expires in 3 days;
                            you can make a new one anytime.
                        </p>
                    )}

                    <form
                        className="mt-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            generateForm.post('/referrals', {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <Button type="submit" disabled={generateForm.processing}>
                            {share ? 'Generate a new invite' : 'Generate invite'}
                        </Button>
                    </form>

                    <form
                        className="mt-6 space-y-3 border-t border-border/70 pt-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            emailForm.post('/referrals/email', {
                                preserveScroll: true,
                                onSuccess: () => emailForm.reset('email'),
                            });
                        }}
                    >
                        <Label htmlFor="email">Email an invite</Label>
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Input
                                id="email"
                                type="email"
                                required
                                placeholder="owner@shop.com"
                                value={emailForm.data.email}
                                onChange={(event) =>
                                    emailForm.setData('email', event.target.value)
                                }
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={emailForm.processing}
                            >
                                <Mail className="size-4" />
                                Send email
                            </Button>
                        </div>
                        <InputError message={emailForm.errors.email} />
                    </form>
                </section>

                <section className="rounded-2xl border border-border/80 bg-card/80">
                    <div className="border-b border-border/70 px-4 py-3 font-medium">
                        People you invited
                    </div>
                    {referrals.length === 0 ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            No one has used your invite yet.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border/70">
                            {referrals.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex flex-col gap-2 p-4 text-sm sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {item.business_name ?? 'New shop'}
                                        </p>
                                        <p className="text-muted-foreground">
                                            Joined {item.onboarded_at}
                                            {item.qualifies_at
                                                ? ` · qualifies ${item.qualifies_at}`
                                                : ''}
                                        </p>
                                    </div>
                                    <Badge variant="secondary">{item.status}</Badge>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

ReferralsIndex.layout = {
    breadcrumbs: [{ title: 'Referrals', href: '/referrals' }],
};
