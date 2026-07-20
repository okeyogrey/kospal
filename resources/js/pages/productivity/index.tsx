import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import { Download, FileSpreadsheet, Upload } from 'lucide-react';
import ProductivityController from '@/actions/App/Http/Controllers/ProductivityController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/productivity';

type Entity = {
    key: string;
    label: string;
    columns: string[];
    can_import_csv: boolean;
    can_import_excel: boolean;
    can_export_csv: boolean;
    can_export_excel: boolean;
};

export default function ProductivityIndex({
    entities,
    features,
    plan,
}: {
    entities: Entity[];
    features: {
        csv_import: boolean;
        excel_import: boolean;
        csv_export: boolean;
        excel_export: boolean;
        pdf_reports: boolean;
    };
    plan: string;
}) {
    const flash = usePage().props.flash as
        | { success?: string; error?: string; import_errors?: string[] }
        | undefined;
    const fileRef = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Productivity tools" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Productivity tools"
                    description="Import and export catalog data, download templates, and manage bulk updates."
                />

                {flash?.success ? (
                    <Alert>
                        <AlertTitle>Import complete</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}

                {flash?.import_errors && flash.import_errors.length > 0 ? (
                    <Alert variant="destructive">
                        <AlertTitle>Some rows failed</AlertTitle>
                        <AlertDescription>
                            <ul className="mt-2 list-disc space-y-1 pl-4">
                                {flash.import_errors.map((error) => (
                                    <li key={error}>{error}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="flex flex-wrap gap-2">
                    <Badge variant="outline">Plan: {plan}</Badge>
                    {features.csv_import ? (
                        <Badge>CSV import</Badge>
                    ) : null}
                    {features.excel_import ? (
                        <Badge>Excel import</Badge>
                    ) : null}
                    {features.excel_export ? (
                        <Badge>Excel export</Badge>
                    ) : null}
                    {features.pdf_reports ? (
                        <Badge>PDF reports</Badge>
                    ) : null}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {entities.map((entity) => (
                        <section
                            key={entity.key}
                            className="space-y-4 rounded-2xl border border-border/80 bg-card/80 p-5 shadow-sm"
                        >
                            <div>
                                <h2 className="font-display text-lg font-semibold">
                                    {entity.label}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Columns: {entity.columns.join(', ')}
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {entity.can_export_csv ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <a
                                            href={`/productivity/${entity.key}/export/csv`}
                                        >
                                            <Download className="size-4" />
                                            Export CSV
                                        </a>
                                    </Button>
                                ) : null}
                                {entity.can_export_excel ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <a
                                            href={`/productivity/${entity.key}/export/xlsx`}
                                        >
                                            <FileSpreadsheet className="size-4" />
                                            Export Excel
                                        </a>
                                    </Button>
                                ) : null}
                                <Button variant="ghost" size="sm" asChild>
                                    <a
                                        href={`/productivity/${entity.key}/template/csv`}
                                    >
                                        CSV template
                                    </a>
                                </Button>
                                <Button variant="ghost" size="sm" asChild>
                                    <a
                                        href={`/productivity/${entity.key}/template/xlsx`}
                                    >
                                        Excel template
                                    </a>
                                </Button>
                            </div>

                            {entity.can_import_csv || entity.can_import_excel ? (
                                <Form
                                    {...ProductivityController.import.form()}
                                    encType="multipart/form-data"
                                    options={{ preserveScroll: true }}
                                    className="space-y-3 border-t border-border/70 pt-4"
                                    onSubmit={() => {
                                        if (fileRef.current) {
                                            fileRef.current.value = '';
                                        }
                                    }}
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="entity"
                                                value={entity.key}
                                            />

                                            <div className="grid gap-2">
                                                <Label htmlFor={`format-${entity.key}`}>
                                                    Import format
                                                </Label>
                                                <select
                                                    id={`format-${entity.key}`}
                                                    name="format"
                                                    defaultValue={
                                                        entity.can_import_csv
                                                            ? 'csv'
                                                            : 'xlsx'
                                                    }
                                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                                >
                                                    {entity.can_import_csv ? (
                                                        <option value="csv">
                                                            CSV
                                                        </option>
                                                    ) : null}
                                                    {entity.can_import_excel ? (
                                                        <option value="xlsx">
                                                            Excel (.xlsx)
                                                        </option>
                                                    ) : null}
                                                </select>
                                                <InputError
                                                    message={errors.format}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`file-${entity.key}`}
                                                >
                                                    Spreadsheet file
                                                </Label>
                                                <input
                                                    ref={fileRef}
                                                    id={`file-${entity.key}`}
                                                    name="file"
                                                    type="file"
                                                    accept=".csv,.txt,.xlsx"
                                                    required
                                                    className="text-sm"
                                                />
                                                <InputError
                                                    message={errors.file}
                                                />
                                            </div>

                                            <div className="flex items-center gap-3">
                                                <input
                                                    id={`update-${entity.key}`}
                                                    type="checkbox"
                                                    name="update_existing"
                                                    value="1"
                                                    className="border-input size-4 rounded border"
                                                />
                                                <Label
                                                    htmlFor={`update-${entity.key}`}
                                                >
                                                    Update existing rows when SKU
                                                    / phone matches
                                                </Label>
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                <Upload className="size-4" />
                                                Import {entity.label.toLowerCase()}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Upgrade to Pro for import/export on this
                                    entity.
                                </p>
                            )}
                        </section>
                    ))}
                </div>

                <p className="text-sm text-muted-foreground">
                    PDF report export is available from each report page. Desktop
                    wizards live under{' '}
                    <Link href="/settings/backup/wizard" className="underline">
                        Backup wizard
                    </Link>
                    ,{' '}
                    <Link href="/settings/printer/wizard" className="underline">
                        Printer wizard
                    </Link>
                    , and{' '}
                    <Link href="/settings/health" className="underline">
                        System health
                    </Link>
                    .
                </p>
            </div>
        </>
    );
}

ProductivityIndex.layout = {
    breadcrumbs: [{ title: 'Productivity', href: index() }],
};
