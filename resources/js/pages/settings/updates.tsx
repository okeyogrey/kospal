import { Form, Head, usePage } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import UpdateManagerController from '@/actions/App/Http/Controllers/Settings/UpdateManagerController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/updates';

type CheckResult = {
    available: boolean;
    version: string | null;
    notes: string | null;
} | null;

export default function UpdateManager({
    supported,
    current_version,
    channel,
    feed_configured,
    check,
}: {
    supported: boolean;
    current_version: string;
    channel: string;
    feed_configured: boolean;
    check: CheckResult;
}) {
    const flash = usePage().props.flash as
        | { success?: string; error?: string }
        | undefined;

    return (
        <>
            <Head title="Update manager" />

            <h1 className="sr-only">Update manager</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Update manager"
                    description="Check for desktop releases and choose your update channel."
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
                        <Badge variant="secondary">v{current_version}</Badge>
                        <Badge variant="outline">{channel}</Badge>
                        {feed_configured ? (
                            <Badge>Feed configured</Badge>
                        ) : (
                            <Badge variant="outline">No feed URL</Badge>
                        )}
                    </div>

                    {!supported ? (
                        <Alert>
                            <AlertTitle>Not available</AlertTitle>
                            <AlertDescription>
                                Update checks are disabled for this deployment.
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {check ? (
                        <Alert
                            variant={
                                check.available ? 'default' : 'default'
                            }
                        >
                            <AlertTitle>
                                {check.available
                                    ? `Update available${check.version ? `: ${check.version}` : ''}`
                                    : 'No update available'}
                            </AlertTitle>
                            <AlertDescription>
                                {check.notes ??
                                    'Check completed successfully.'}
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    <Form
                        {...UpdateManagerController.check.form()}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                disabled={processing || !supported}
                            >
                                <RefreshCw className="size-4" />
                                Check for updates
                            </Button>
                        )}
                    </Form>
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Channel"
                        description="Stable is recommended for stores. Beta may include newer fixes."
                    />
                    <Form
                        {...UpdateManagerController.updateChannel.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="update_channel">
                                        Release channel
                                    </Label>
                                    <select
                                        id="update_channel"
                                        name="update_channel"
                                        defaultValue={channel}
                                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    >
                                        <option value="stable">Stable</option>
                                        <option value="beta">Beta</option>
                                    </select>
                                    <InputError message={errors.update_channel} />
                                </div>
                                <Button type="submit" disabled={processing}>
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

UpdateManager.layout = {
    breadcrumbs: [{ title: 'Updates', href: edit() }],
};
