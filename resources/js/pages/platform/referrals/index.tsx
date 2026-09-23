import { Head, useForm, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type ReferralRow = {
    id: number;
    status: string;
    onboarded_at: string | null;
    qualifies_at: string | null;
    qualified_at: string | null;
    void_reason: string | null;
    voided_by: string | null;
    referrer: {
        business_id: number | null;
        name: string | null;
        email: string | null;
    };
    referred: {
        business_id: number | null;
        name: string | null;
        email: string | null;
        is_active: boolean | null;
    };
    credits: {
        side: string;
        percent: number;
        remaining_percent: number;
        status: string;
    }[];
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function PlatformReferralsIndex({
    referrals,
    filters,
    statuses,
}: {
    referrals: Paginated<ReferralRow>;
    filters: { status: string };
    statuses: string[];
}) {
    const flash = usePage().props.flash as
        | { success?: string; error?: string }
        | undefined;

    return (
        <>
            <Head title="Referral review" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold">
                        Referrals
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Track invite redemptions and void unused credits when a
                        referral looks invalid.
                    </p>
                </div>

                {flash?.success ? (
                    <p className="text-sm text-primary">{flash.success}</p>
                ) : null}

                <form className="flex flex-wrap gap-2" method="get">
                    {['all', ...statuses].map((status) => (
                        <Button
                            key={status}
                            type="submit"
                            name="status"
                            value={status}
                            variant={
                                filters.status === status ? 'default' : 'outline'
                            }
                            size="sm"
                        >
                            {status}
                        </Button>
                    ))}
                </form>

                <section className="rounded-2xl border border-border/80 bg-card/80">
                    {referrals.data.length === 0 ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            No referrals yet.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border/70">
                            {referrals.data.map((item) => (
                                <ReferralReviewRow key={item.id} item={item} />
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

function ReferralReviewRow({ item }: { item: ReferralRow }) {
    const form = useForm({ reason: '' });

    return (
        <li className="space-y-3 p-4 text-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-medium">
                        {item.referrer.name ?? 'Referrer'} →{' '}
                        {item.referred.name ?? 'Invitee'}
                    </p>
                    <p className="text-muted-foreground">
                        {item.referrer.email} invited {item.referred.email}
                    </p>
                    <p className="text-muted-foreground">
                        Onboarded {item.onboarded_at?.slice(0, 10)} · qualifies{' '}
                        {item.qualifies_at?.slice(0, 10)}
                    </p>
                </div>
                <Badge variant="secondary">{item.status}</Badge>
            </div>
            {item.credits.length > 0 ? (
                <p className="text-muted-foreground">
                    Credits:{' '}
                    {item.credits
                        .map(
                            (credit) =>
                                `${credit.side} ${credit.remaining_percent}/${credit.percent}% ${credit.status}`,
                        )
                        .join(' · ')}
                </p>
            ) : null}
            {item.status !== 'voided' ? (
                <form
                    className="flex flex-col gap-2 sm:flex-row"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/platform/referrals/${item.id}/void`, {
                            preserveScroll: true,
                        });
                    }}
                >
                    <Input
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        placeholder="Reason (optional)"
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={form.processing}
                    >
                        Void unused credits
                    </Button>
                </form>
            ) : (
                <p className="text-muted-foreground">
                    Voided{item.voided_by ? ` by ${item.voided_by}` : ''}
                    {item.void_reason ? ` · ${item.void_reason}` : ''}
                </p>
            )}
        </li>
    );
}

PlatformReferralsIndex.layout = {
    breadcrumbs: [{ title: 'Referrals', href: '/platform/referrals' }],
};
