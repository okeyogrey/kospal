import { Form, Head, usePage } from '@inertiajs/react';
import { Database, FileDown, Wrench } from 'lucide-react';
import DatabaseSettingsController from '@/actions/App/Http/Controllers/Settings/DatabaseSettingsController';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/database';

export default function DatabaseSettings({
    connection,
    schema,
    sqlite,
    supported_drivers,
    data_directory,
}: {
    connection: {
        name: string;
        driver: string;
        path: string | null;
        is_sqlite: boolean;
    };
    schema: { current: number | null; expected: number };
    sqlite: {
        journal_mode: string;
        synchronous: string;
        busy_timeout: number;
        foreign_keys: boolean;
    };
    supported_drivers: string[];
    data_directory: string | null;
}) {
    const flash = usePage().props.flash as
        | { success?: string; error?: string }
        | undefined;

    const schemaOk = schema.current === schema.expected;

    return (
        <>
            <Head title="Database configuration" />

            <h1 className="sr-only">Database configuration</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Database"
                    description="Connection details and maintenance tools for this installation."
                />

                {flash?.success ? (
                    <Alert>
                        <AlertTitle>Database</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}

                {flash?.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Database error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                <section className="space-y-3">
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="outline">{connection.driver}</Badge>
                        <Badge variant={schemaOk ? 'secondary' : 'destructive'}>
                            schema {schema.current ?? '—'} / {schema.expected}
                        </Badge>
                    </div>

                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground">Connection</dt>
                            <dd className="font-medium">{connection.name}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Supported drivers
                            </dt>
                            <dd className="font-medium">
                                {supported_drivers.join(', ')}
                            </dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-muted-foreground">
                                Database path
                            </dt>
                            <dd className="font-mono text-xs break-all">
                                {connection.path ?? 'In-memory / remote'}
                            </dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-muted-foreground">
                                Data directory
                            </dt>
                            <dd className="font-mono text-xs break-all">
                                {data_directory || 'Default application storage'}
                            </dd>
                        </div>
                    </dl>
                </section>

                {connection.is_sqlite ? (
                    <section className="space-y-3">
                        <Heading
                            variant="small"
                            title="SQLite pragmas"
                            description="Configured at startup for desktop installs."
                        />
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground">
                                    Journal mode
                                </dt>
                                <dd className="font-medium">
                                    {sqlite.journal_mode}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Synchronous
                                </dt>
                                <dd className="font-medium">
                                    {sqlite.synchronous}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Busy timeout
                                </dt>
                                <dd className="font-medium">
                                    {sqlite.busy_timeout} ms
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Foreign keys
                                </dt>
                                <dd className="font-medium">
                                    {sqlite.foreign_keys ? 'On' : 'Off'}
                                </dd>
                            </div>
                        </dl>
                    </section>
                ) : null}

                <section className="space-y-3">
                    <Heading
                        variant="small"
                        title="Maintenance"
                        description="Safe operations that stay outside retail modules."
                    />

                    <div className="flex flex-col gap-3">
                        <Form
                            {...DatabaseSettingsController.migrate.form()}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                    className="justify-start"
                                >
                                    <Database className="size-4" />
                                    Apply pending migrations
                                </Button>
                            )}
                        </Form>

                        <Form
                            {...DatabaseSettingsController.repair.form()}
                            options={{ preserveScroll: true }}
                            className="space-y-2"
                        >
                            {({ processing }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="vacuum"
                                        value="1"
                                    />
                                    <input
                                        type="hidden"
                                        name="reindex"
                                        value="1"
                                    />
                                    <input
                                        type="hidden"
                                        name="backup_first"
                                        value="1"
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                        className="justify-start"
                                    >
                                        <Wrench className="size-4" />
                                        Repair (backup, reindex, vacuum)
                                    </Button>
                                </>
                            )}
                        </Form>

                        <Form
                            {...DatabaseSettingsController.export.form()}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                    className="justify-start"
                                >
                                    <FileDown className="size-4" />
                                    Export portable SQL
                                </Button>
                            )}
                        </Form>
                    </div>
                </section>
            </div>
        </>
    );
}

DatabaseSettings.layout = {
    breadcrumbs: [{ title: 'Database', href: edit() }],
};
