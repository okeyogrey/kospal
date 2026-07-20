import { Form, Head, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    Database,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { edit } from '@/routes/health';

type Diagnosis = {
    ok: boolean;
    driver: string;
    connection: string;
    database: string | null;
    schema_version: number | null;
    expected_schema_version: number;
    migrations: { ran: number; pending: string[] };
    integrity: { ok: boolean; messages: string[] };
    foreign_keys: { ok: boolean; violations: string[] };
    tables: { name: string; rows: number | null }[];
    size_bytes: number | null;
    issues: string[];
};

function formatBytes(bytes: number | null): string {
    if (bytes === null) {
        return '—';
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function StatusBadge({ ok }: { ok: boolean }) {
    return ok ? (
        <Badge>
            <CheckCircle2 className="size-3" />
            Healthy
        </Badge>
    ) : (
        <Badge variant="destructive">
            <AlertTriangle className="size-3" />
            Attention
        </Badge>
    );
}

export default function HealthDashboard({
    diagnosis,
    backup,
    updates,
    storage,
}: {
    diagnosis: Diagnosis;
    backup: {
        supported: boolean;
        count: number;
        latest: {
            id: string;
            created_at: string;
            size_bytes: number | null;
        } | null;
        auto_backup: boolean;
    };
    updates: {
        supported: boolean;
        current_version: string;
        channel: string;
    };
    storage: {
        data_directory: string | null;
        settings_path: string | null;
    };
}) {
    return (
        <>
            <Head title="Health dashboard" />

            <h1 className="sr-only">Health dashboard</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Health dashboard"
                    description="Database integrity, schema, backups, and update status for this install."
                />

                <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge ok={diagnosis.ok} />
                    <Badge variant="outline">{diagnosis.driver}</Badge>
                    <Badge variant="secondary">
                        schema {diagnosis.schema_version ?? '—'} /{' '}
                        {diagnosis.expected_schema_version}
                    </Badge>
                </div>

                {diagnosis.issues.length > 0 ? (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>Issues detected</AlertTitle>
                        <AlertDescription>
                            <ul className="mt-2 list-disc space-y-1 pl-4">
                                {diagnosis.issues.map((issue) => (
                                    <li key={issue}>{issue}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                ) : (
                    <Alert>
                        <Activity className="size-4" />
                        <AlertTitle>All checks passed</AlertTitle>
                        <AlertDescription>
                            Integrity, foreign keys, and migrations look good.
                        </AlertDescription>
                    </Alert>
                )}

                <section className="space-y-3">
                    <Heading
                        variant="small"
                        title="Database"
                        description="Live connection diagnostics."
                    />
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground">Connection</dt>
                            <dd className="font-medium">{diagnosis.connection}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Size</dt>
                            <dd className="font-medium">
                                {formatBytes(diagnosis.size_bytes)}
                            </dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-muted-foreground">Path</dt>
                            <dd className="font-mono text-xs break-all">
                                {diagnosis.database ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Integrity</dt>
                            <dd className="font-medium">
                                {diagnosis.integrity.ok ? 'OK' : 'Failed'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Foreign keys
                            </dt>
                            <dd className="font-medium">
                                {diagnosis.foreign_keys.ok
                                    ? 'OK'
                                    : `${diagnosis.foreign_keys.violations.length} violation(s)`}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Migrations</dt>
                            <dd className="font-medium">
                                {diagnosis.migrations.ran} ran
                                {diagnosis.migrations.pending.length > 0
                                    ? `, ${diagnosis.migrations.pending.length} pending`
                                    : ''}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Tables</dt>
                            <dd className="font-medium">
                                {diagnosis.tables.length}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section className="space-y-3">
                    <Heading
                        variant="small"
                        title="Backup & updates"
                        description="Operational status outside the retail modules."
                    />
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground">Backups</dt>
                            <dd className="font-medium">
                                {backup.supported
                                    ? `${backup.count} file(s)`
                                    : 'Unsupported'}
                                {backup.auto_backup ? ' · auto on' : ' · auto off'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Latest backup
                            </dt>
                            <dd className="font-mono text-xs break-all">
                                {backup.latest?.id ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">App version</dt>
                            <dd className="font-medium">
                                {updates.current_version}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Channel</dt>
                            <dd className="font-medium">{updates.channel}</dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-muted-foreground">
                                Data directory
                            </dt>
                            <dd className="flex items-start gap-2 font-mono text-xs break-all">
                                <Database className="mt-0.5 size-3.5 shrink-0" />
                                {storage.data_directory ||
                                    'Application storage (default)'}
                            </dd>
                        </div>
                    </dl>
                </section>

                {diagnosis.tables.length > 0 ? (
                    <section className="space-y-3">
                        <Heading
                            variant="small"
                            title="Table stats"
                            description="Row counts for the largest tables."
                        />
                        <ul className="max-h-64 divide-y overflow-y-auto rounded-md border text-sm">
                            {[...diagnosis.tables]
                                .sort(
                                    (a, b) => (b.rows ?? 0) - (a.rows ?? 0),
                                )
                                .slice(0, 12)
                                .map((table) => (
                                    <li
                                        key={table.name}
                                        className="flex items-center justify-between px-3 py-2"
                                    >
                                        <span className="font-mono text-xs">
                                            {table.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {table.rows ?? '—'}
                                        </span>
                                    </li>
                                ))}
                        </ul>
                    </section>
                ) : null}
            </div>
        </>
    );
}

HealthDashboard.layout = {
    breadcrumbs: [{ title: 'Health', href: edit() }],
};
