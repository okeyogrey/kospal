import { Form, Head, usePage } from '@inertiajs/react';
import { DatabaseBackup } from 'lucide-react';
import BackupController from '@/actions/App/Http/Controllers/Settings/BackupController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { EmptyState } from '@/components/states/empty-state';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/backup';

type BackupRow = {
    id: string;
    label: string | null;
    created_at: string;
    size_bytes: number | null;
};

function formatBytes(bytes: number | null): string {
    if (bytes === null) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function BackupSettings({
    supported,
    backups,
    settings,
}: {
    supported: boolean;
    backups: BackupRow[];
    settings: { auto_backup: boolean; auto_backup_hour: number };
}) {
    const flash = usePage().props.flash as
        | { success?: string; error?: string }
        | undefined;

    return (
        <>
            <Head title="Backup settings" />

            <h1 className="sr-only">Backup settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Automatic backup"
                    description="Create SQLite file backups and schedule daily copies on this machine."
                />

                {flash?.success ? (
                    <Alert>
                        <AlertTitle>Backup</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}

                {flash?.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Backup failed</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                {!supported ? (
                    <Alert>
                        <AlertTitle>Not available</AlertTitle>
                        <AlertDescription>
                            File backups require a file-backed SQLite database.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <section className="space-y-4">
                    <Form
                        {...BackupController.updateSettings.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="flex items-center gap-3">
                                    <input
                                        type="hidden"
                                        name="auto_backup"
                                        value="0"
                                    />
                                    <input
                                        id="auto_backup"
                                        type="checkbox"
                                        name="auto_backup"
                                        value="1"
                                        defaultChecked={settings.auto_backup}
                                        className="border-input size-4 rounded border"
                                    />
                                    <Label htmlFor="auto_backup">
                                        Run automatic daily backup
                                    </Label>
                                </div>
                                <InputError message={errors.auto_backup} />

                                <div className="grid gap-2">
                                    <Label htmlFor="auto_backup_hour">
                                        Backup hour (0–23, local time)
                                    </Label>
                                    <Input
                                        id="auto_backup_hour"
                                        name="auto_backup_hour"
                                        type="number"
                                        min={0}
                                        max={23}
                                        defaultValue={settings.auto_backup_hour}
                                        className="w-28"
                                    />
                                    <InputError
                                        message={errors.auto_backup_hour}
                                    />
                                </div>

                                <Button type="submit" disabled={processing}>
                                    Save schedule
                                </Button>
                            </>
                        )}
                    </Form>
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Create backup now"
                        description="Copies the current database into the backups folder."
                    />
                    <Form
                        {...BackupController.create.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                        resetOnSuccess
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="label">Label (optional)</Label>
                                    <Input
                                        id="label"
                                        name="label"
                                        placeholder="before-inventory"
                                        disabled={!supported}
                                    />
                                    <InputError message={errors.label} />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={processing || !supported}
                                >
                                    <DatabaseBackup className="size-4" />
                                    Create backup
                                </Button>
                            </>
                        )}
                    </Form>
                </section>

                <section className="space-y-3">
                    <Heading
                        variant="small"
                        title="Recent backups"
                        description="Newest first. Use Restore to apply one."
                    />

                    {backups.length === 0 ? (
                        <EmptyState
                            title="No backups yet"
                            description="Create a backup or enable the daily schedule."
                            className="min-h-[10rem] py-8"
                        />
                    ) : (
                        <ul className="divide-y rounded-md border text-sm">
                            {backups.map((backup) => (
                                <li
                                    key={backup.id}
                                    className="flex flex-col gap-1 px-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-mono text-xs break-all">
                                            {backup.id}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {new Date(
                                                backup.created_at,
                                            ).toLocaleString()}
                                        </p>
                                    </div>
                                    <Badge variant="outline">
                                        {formatBytes(backup.size_bytes)}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

BackupSettings.layout = {
    breadcrumbs: [{ title: 'Backup', href: edit() }],
};
