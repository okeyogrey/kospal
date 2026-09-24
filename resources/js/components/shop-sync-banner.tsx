import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type ConflictNotice = {
    sale_id: number | null;
    sale_number: string | null;
    message: string;
};

export function ShopSyncBanner() {
    const { shopSync } = usePage().props;
    const [conflicts, setConflicts] = useState<ConflictNotice[]>([]);

    useEffect(() => {
        if (
            !shopSync?.linked &&
            !shopSync?.shared &&
            shopSync?.conflicts === 0
        ) {
            return;
        }

        let stopped = false;

        const load = () => {
            fetch('/settings/shops/status', {
                headers: { Accept: 'application/json' },
            })
                .then(async (response) => {
                    if (!response.ok) {
                        return null;
                    }

                    return (await response.json()) as {
                        conflicts?: ConflictNotice[];
                    };
                })
                .then((body) => {
                    if (stopped || body === null) {
                        return;
                    }

                    setConflicts(
                        Array.isArray(body.conflicts) ? body.conflicts : [],
                    );
                })
                .catch(() => undefined);
        };

        load();
        const timer = window.setInterval(() => {
            load();

            if (!shopSync?.shared && !shopSync?.linked) {
                return;
            }

            if (document.hidden) {
                return;
            }

            const active = document.activeElement;

            if (
                active instanceof HTMLInputElement ||
                active instanceof HTMLTextAreaElement ||
                active instanceof HTMLSelectElement
            ) {
                return;
            }

            router.reload({
                preserveScroll: true,
                preserveState: true,
            });
        }, 20000);

        return () => {
            stopped = true;
            window.clearInterval(timer);
        };
    }, [shopSync?.conflicts, shopSync?.linked, shopSync?.shared]);

    const first = conflicts[0];

    if (!first) {
        return null;
    }

    return (
        <div className="px-4 pt-4">
            <Alert variant="destructive">
                <AlertTitle>
                    {conflicts.length === 1
                        ? 'A sale needs a refund'
                        : `${conflicts.length} sales need a refund`}
                </AlertTitle>
                <AlertDescription>
                    <p>{first.message}</p>
                    {first.sale_id && (
                        <Link
                            href={`/sales/${first.sale_id}`}
                            className="mt-2 inline-block font-medium underline"
                        >
                            Open {first.sale_number ?? 'the sale'}
                        </Link>
                    )}
                </AlertDescription>
            </Alert>
        </div>
    );
}
