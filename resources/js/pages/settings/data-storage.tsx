import { Form, Head, usePage } from '@inertiajs/react';
import { FolderOpen } from 'lucide-react';
import StorageSettingsController from '@/actions/App/Http/Controllers/Settings/StorageSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/data-storage';

function formatBytes(bytes: number | null): string {
    if (bytes === null) {
        return '—';
    }

    const gb = bytes / (1024 * 1024 * 1024);

    if (gb >= 1) {
        return `${gb.toFixed(1)} GB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(0)} MB`;
}

export default function DataStorageSettings({
    settings,
    paths,
    disk,
}: {
    settings: { data_directory: string };
    paths: {
        data_directory: string | null;
        backup_directory: string;
        export_directory: string;
        settings_file: string;
    };
    disk: {
        path: string;
        free_bytes: number | null;
        total_bytes: number | null;
    };
}) {
    const flash = usePage().props.flash as
        | { success?: string; error?: string }
        | undefined;

    return (
        <>
            <Head title="Storage configuration" />

            <h1 className="sr-only">Storage configuration</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Storage"
                    description="Where this installation stores backups, exports, and local data."
                />

                {flash?.success ? (
                    <Alert>
                        <AlertTitle>Saved</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}

                {flash?.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                <section className="space-y-3">
                    <dl className="grid gap-3 text-sm">
                        <div>
                            <dt className="text-muted-foreground">
                                Backup folder
                            </dt>
                            <dd className="font-mono text-xs break-all">
                                {paths.backup_directory}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Export folder
                            </dt>
                            <dd className="font-mono text-xs break-all">
                                {paths.export_directory}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Settings file
                            </dt>
                            <dd className="font-mono text-xs break-all">
                                {paths.settings_file}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Disk space</dt>
                            <dd className="font-medium">
                                {formatBytes(disk.free_bytes)} free of{' '}
                                {formatBytes(disk.total_bytes)}
                            </dd>
                        </div>
                    </dl>
                </section>

                <Form
                    {...StorageSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="data_directory">
                                    Data directory
                                </Label>
                                <Input
                                    id="data_directory"
                                    name="data_directory"
                                    defaultValue={settings.data_directory}
                                    placeholder="C:\KOSPAL\data"
                                    className="font-mono text-xs"
                                />
                                <p className="text-muted-foreground text-xs">
                                    Leave empty to use the application storage
                                    folder. Backups and exports are created under
                                    this path.
                                </p>
                                <InputError message={errors.data_directory} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                <FolderOpen className="size-4" />
                                Save storage settings
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

DataStorageSettings.layout = {
    breadcrumbs: [{ title: 'Storage', href: edit() }],
};
