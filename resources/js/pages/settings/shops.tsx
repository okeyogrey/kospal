import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ConflictRow = {
    id: number;
    sale_id: number | null;
    sale_number: string | null;
    message: string;
    created_at: string | null;
};

type ShopsProps = {
    serverUrl: string;
    linked: boolean;
    joinCode: string | null;
    lastSyncedAt: string | null;
    lastError: string | null;
    pending: number;
    conflicts: ConflictRow[];
};

export default function ShopsSettings({
    serverUrl,
    linked,
    joinCode,
    lastSyncedAt,
    lastError,
    pending,
    conflicts,
}: ShopsProps) {
    const { flash } = usePage().props;
    const linkForm = useForm({
        server_url: serverUrl,
        device_name: 'This computer',
    });
    const joinForm = useForm({
        server_url: serverUrl,
        join_code: '',
    });
    const [syncing, setSyncing] = useState(false);

    return (
        <>
            <Head title="Shops" />

            <h1 className="sr-only">Shops</h1>

            <div className="space-y-6">
                <Heading
                    title="Shops"
                    description="Connect this computer to the office. The office keeps the shared products, stock, and sales, so another location sees the same shop. This computer still sells if the internet drops, then sends those changes when it reconnects."
                />

                {flash.success && (
                    <Alert>
                        <AlertTitle>Saved</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {flash.error && (
                    <Alert variant="destructive">
                        <AlertTitle>Could not sync</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                {lastError && (
                    <Alert variant="destructive">
                        <AlertTitle>Last sync failed</AlertTitle>
                        <AlertDescription>
                            {lastError} Sales on this computer are still saved.
                            They will send the next time this shop can reach the
                            server.
                        </AlertDescription>
                    </Alert>
                )}

                {conflicts.length > 0 && (
                    <Alert variant="destructive">
                        <AlertTitle>Sales to refund</AlertTitle>
                        <AlertDescription>
                            <p>
                                Another computer sold stock this till also sold.
                                Void each sale below and refund the customer. If
                                they still have the item, receive it back into
                                stock after the refund.
                            </p>
                            <ul className="mt-3 space-y-2">
                                {conflicts.map((conflict) => (
                                    <li key={conflict.id}>
                                        {conflict.sale_id ? (
                                            <a
                                                className="font-medium underline"
                                                href={`/sales/${conflict.sale_id}`}
                                            >
                                                {conflict.sale_number ??
                                                    'Open sale'}
                                            </a>
                                        ) : (
                                            <span className="font-medium">
                                                Stock change
                                            </span>
                                        )}
                                        <span className="mt-1 block text-sm">
                                            {conflict.message}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                {linked ? (
                    <div className="space-y-4 rounded-md border border-border/80 p-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Join code for another computer
                            </p>
                            <p className="mt-1 font-mono text-2xl tracking-widest">
                                {joinCode}
                            </p>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {pending === 0
                                ? 'Nothing is waiting to send.'
                                : `${pending} change${pending === 1 ? '' : 's'} waiting to send.`}
                            {lastSyncedAt
                                ? ` Last synced ${new Date(lastSyncedAt).toLocaleString()}.`
                                : ' Not synced yet.'}{' '}
                            While the desktop app is open it syncs about once a
                            minute. Each computer still needs its own license.
                            Backups stay on this computer.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                disabled={syncing}
                                onClick={() => {
                                    setSyncing(true);
                                    router.post(
                                        '/settings/shops/sync',
                                        {},
                                        {
                                            preserveScroll: true,
                                            onFinish: () => setSyncing(false),
                                        },
                                    );
                                }}
                            >
                                {syncing ? 'Syncing…' : 'Sync now'}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.post('/settings/shops/join-code')
                                }
                            >
                                New join code
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="grid gap-8 lg:grid-cols-2">
                        <form
                            className="space-y-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                linkForm.post('/settings/shops/link');
                            }}
                        >
                            <div>
                                <h2 className="text-base font-medium">
                                    Link this shop
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Use this on the first computer. The shop
                                    address is already filled in. It creates
                                    the shared shop and a join code for the
                                    others.
                                </p>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="link-server">
                                    Shop server address
                                </Label>
                                <Input
                                    id="link-server"
                                    value={linkForm.data.server_url}
                                    onChange={(event) =>
                                        linkForm.setData(
                                            'server_url',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="https://software.kospal.com"
                                />
                                <InputError
                                    message={linkForm.errors.server_url}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="device-name">
                                    Name for this computer
                                </Label>
                                <Input
                                    id="device-name"
                                    value={linkForm.data.device_name}
                                    onChange={(event) =>
                                        linkForm.setData(
                                            'device_name',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={linkForm.processing}
                            >
                                Link this shop
                            </Button>
                        </form>

                        <form
                            className="space-y-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                joinForm.post('/settings/shops/join');
                            }}
                        >
                            <div>
                                <h2 className="text-base font-medium">
                                    Join an existing shop
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Use this on a new computer before you add
                                    products or make a sale. Leave the address
                                    as it is, type the join code, then sign in
                                    with the existing staff email and password.
                                </p>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="join-server">
                                    Shop server address
                                </Label>
                                <Input
                                    id="join-server"
                                    value={joinForm.data.server_url}
                                    onChange={(event) =>
                                        joinForm.setData(
                                            'server_url',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="https://software.kospal.com"
                                />
                                <InputError
                                    message={joinForm.errors.server_url}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="join-code">Join code</Label>
                                <Input
                                    id="join-code"
                                    value={joinForm.data.join_code}
                                    onChange={(event) =>
                                        joinForm.setData(
                                            'join_code',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    autoCapitalize="characters"
                                />
                                <InputError
                                    message={joinForm.errors.join_code}
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={joinForm.processing}
                            >
                                Join shop
                            </Button>
                        </form>
                    </div>
                )}
            </div>
        </>
    );
}

ShopsSettings.layout = {
    breadcrumbs: [{ title: 'Shops', href: '/settings/shops' }],
};
