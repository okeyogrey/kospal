import { Form, Head, usePage } from '@inertiajs/react';
import { HardDrive } from 'lucide-react';
import LocalSettingsController from '@/actions/App/Http/Controllers/Settings/LocalSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/local';

type LocalSettings = {
    update_channel: string;
    auto_backup: boolean;
    auto_backup_hour: number;
    printer_name: string | null;
    receipt_width: string;
    data_directory: string | null;
};

export default function LocalSettingsPage({
    settings,
    deployment,
}: {
    settings: LocalSettings;
    deployment: { mode: string; version: string };
}) {
    const flash = usePage().props.flash as
        | { success?: string; error?: string }
        | undefined;

    return (
        <>
            <Head title="Local settings" />

            <h1 className="sr-only">Local settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Local settings"
                    description="Installation preferences for this desktop machine."
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
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">{deployment.mode}</Badge>
                        <Badge variant="secondary">v{deployment.version}</Badge>
                    </div>

                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground">
                                Automatic backup
                            </dt>
                            <dd className="font-medium">
                                {settings.auto_backup
                                    ? `On (daily at ${String(settings.auto_backup_hour).padStart(2, '0')}:00)`
                                    : 'Off'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Printer</dt>
                            <dd className="font-medium">
                                {settings.printer_name || 'System default'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Receipt width
                            </dt>
                            <dd className="font-medium">
                                {settings.receipt_width} mm
                            </dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-muted-foreground">
                                Data directory
                            </dt>
                            <dd className="font-mono text-xs break-all">
                                {settings.data_directory ||
                                    'Application storage (default)'}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Update channel"
                        description="Choose which release channel this installation follows."
                    />
                    <Form
                        {...LocalSettingsController.update.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="update_channel">
                                        Channel
                                    </Label>
                                    <select
                                        id="update_channel"
                                        name="update_channel"
                                        defaultValue={settings.update_channel}
                                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    >
                                        <option value="stable">Stable</option>
                                        <option value="beta">Beta</option>
                                    </select>
                                    <InputError message={errors.update_channel} />
                                </div>
                                <Button type="submit" disabled={processing}>
                                    <HardDrive className="size-4" />
                                    Save channel
                                </Button>
                            </>
                        )}
                    </Form>
                </section>
            </div>
        </>
    );
}

LocalSettingsPage.layout = {
    breadcrumbs: [{ title: 'Local settings', href: edit() }],
};
