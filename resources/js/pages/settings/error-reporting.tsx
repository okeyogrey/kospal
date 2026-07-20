import { Form, Head, usePage } from '@inertiajs/react';
import { AlertTriangle, Download } from 'lucide-react';
import ErrorReportingController from '@/actions/App/Http/Controllers/Settings/ErrorReportingController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit as editErrorReporting } from '@/routes/error-reporting';

type ErrorRow = {
    level: string;
    message: string;
    context: string | null;
    logged_at: string | null;
};

export default function ErrorReportingSettings({
    settings,
    recent_errors,
}: {
    settings: {
        error_reporting_enabled: boolean;
        include_diagnostics: boolean;
    };
    recent_errors: ErrorRow[];
}) {
    const flash = usePage().props.flash as
        | { success?: string }
        | undefined;

    return (
        <>
            <Head title="Error reporting" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Error reporting"
                    description="Review recent application errors and export a diagnostic bundle for support."
                />

                {flash?.success ? (
                    <Alert>
                        <AlertTitle>Saved</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}

                <Form
                    {...ErrorReportingController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="flex items-center gap-3">
                                <input
                                    type="hidden"
                                    name="error_reporting_enabled"
                                    value="0"
                                />
                                <input
                                    id="error_reporting_enabled"
                                    type="checkbox"
                                    name="error_reporting_enabled"
                                    value="1"
                                    defaultChecked={
                                        settings.error_reporting_enabled
                                    }
                                    className="border-input size-4 rounded border"
                                />
                                <Label htmlFor="error_reporting_enabled">
                                    Keep local error reporting enabled
                                </Label>
                            </div>
                            <InputError
                                message={errors.error_reporting_enabled}
                            />

                            <div className="flex items-center gap-3">
                                <input
                                    type="hidden"
                                    name="include_diagnostics"
                                    value="0"
                                />
                                <input
                                    id="include_diagnostics"
                                    type="checkbox"
                                    name="include_diagnostics"
                                    value="1"
                                    defaultChecked={
                                        settings.include_diagnostics
                                    }
                                    className="border-input size-4 rounded border"
                                />
                                <Label htmlFor="include_diagnostics">
                                    Include environment details in diagnostic
                                    exports
                                </Label>
                            </div>
                            <InputError message={errors.include_diagnostics} />

                            <Button type="submit" disabled={processing}>
                                Save preferences
                            </Button>
                        </>
                    )}
                </Form>

                <section className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <Heading
                            variant="small"
                            title="Recent errors"
                            description="Latest entries from the local Laravel log."
                        />
                        <Button variant="outline" size="sm" asChild>
                            <a href="/settings/error-reporting/download">
                                <Download className="size-4" />
                                Export diagnostics
                            </a>
                        </Button>
                    </div>

                    {recent_errors.length === 0 ? (
                        <Alert>
                            <AlertTitle>No recent errors</AlertTitle>
                            <AlertDescription>
                                The application log has no recent error-level
                                entries.
                            </AlertDescription>
                        </Alert>
                    ) : (
                        <ul className="divide-y rounded-md border text-sm">
                            {recent_errors.map((entry, index) => (
                                <li key={`${entry.logged_at}-${index}`} className="space-y-1 px-3 py-3">
                                    <div className="flex items-center gap-2">
                                        <AlertTriangle className="size-4 text-destructive" />
                                        <span className="font-medium">
                                            {entry.level}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {entry.logged_at}
                                        </span>
                                    </div>
                                    <p className="font-mono text-xs break-all">
                                        {entry.message}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

ErrorReportingSettings.layout = {
    breadcrumbs: [{ title: 'Error reporting', href: editErrorReporting() }],
};
