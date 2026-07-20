import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AlertTriangle, CheckCircle2, RotateCcw } from 'lucide-react';
import RestoreController from '@/actions/App/Http/Controllers/Settings/RestoreController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { EmptyState } from '@/components/states/empty-state';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/restore';

type BackupRow = {
    id: string;
    label: string | null;
    created_at: string;
    size_bytes: number | null;
};

type Step = 1 | 2 | 3;

export default function RestoreWizard({
    supported,
    backups,
}: {
    supported: boolean;
    backups: BackupRow[];
}) {
    const flash = usePage().props.flash as
        | { success?: string; error?: string }
        | undefined;
    const [step, setStep] = useState<Step>(flash?.success ? 3 : 1);
    const [selectedId, setSelectedId] = useState<string>(
        backups[0]?.id ?? '',
    );

    const selected = backups.find((backup) => backup.id === selectedId);

    return (
        <>
            <Head title="Restore wizard" />

            <h1 className="sr-only">Restore wizard</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Restore wizard"
                    description="Replace the live database with a previous SQLite backup."
                />

                {flash?.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Restore failed</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                {flash?.success ? (
                    <Alert>
                        <CheckCircle2 className="size-4" />
                        <AlertTitle>Restore complete</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}

                {!supported ? (
                    <Alert>
                        <AlertTitle>Not available</AlertTitle>
                        <AlertDescription>
                            Restore requires a file-backed SQLite database.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <ol className="flex flex-wrap gap-2 text-xs">
                    {[
                        { n: 1, label: 'Select' },
                        { n: 2, label: 'Confirm' },
                        { n: 3, label: 'Done' },
                    ].map((item) => (
                        <li
                            key={item.n}
                            className={
                                step === item.n
                                    ? 'rounded-md bg-primary px-2 py-1 text-primary-foreground'
                                    : 'rounded-md bg-muted px-2 py-1 text-muted-foreground'
                            }
                        >
                            {item.n}. {item.label}
                        </li>
                    ))}
                </ol>

                {step === 1 ? (
                    <section className="space-y-4">
                        {backups.length === 0 ? (
                            <EmptyState
                                title="No backups to restore"
                                description="Create a backup first from Backup settings."
                                className="min-h-[10rem] py-8"
                            />
                        ) : (
                            <ul className="space-y-2">
                                {backups.map((backup) => (
                                    <li key={backup.id}>
                                        <label className="flex cursor-pointer items-start gap-3 rounded-md border px-3 py-3 text-sm has-[:checked]:border-primary">
                                            <input
                                                type="radio"
                                                name="backup_choice"
                                                className="mt-1"
                                                checked={selectedId === backup.id}
                                                onChange={() =>
                                                    setSelectedId(backup.id)
                                                }
                                            />
                                            <span>
                                                <span className="block font-mono text-xs break-all">
                                                    {backup.id}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {new Date(
                                                        backup.created_at,
                                                    ).toLocaleString()}
                                                </span>
                                            </span>
                                        </label>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <Button
                            type="button"
                            disabled={!supported || !selectedId}
                            onClick={() => setStep(2)}
                        >
                            Continue
                        </Button>
                    </section>
                ) : null}

                {step === 2 && selected ? (
                    <section className="space-y-4">
                        <Alert variant="destructive">
                            <AlertTriangle className="size-4" />
                            <AlertTitle>This cannot be undone easily</AlertTitle>
                            <AlertDescription>
                                Restoring{' '}
                                <span className="font-mono text-xs">
                                    {selected.id}
                                </span>{' '}
                                replaces the current database. A pre-restore
                                snapshot is created automatically when possible.
                            </AlertDescription>
                        </Alert>

                        <Form
                            {...RestoreController.restore.form()}
                            options={{ preserveScroll: true }}
                            className="space-y-4"
                            onSuccess={() => setStep(3)}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="backup_id"
                                        value={selected.id}
                                    />

                                    <div className="flex items-center gap-3">
                                        <input
                                            id="confirm"
                                            type="checkbox"
                                            name="confirm"
                                            value="1"
                                            required
                                            className="border-input size-4 rounded border"
                                        />
                                        <Label htmlFor="confirm">
                                            I understand this replaces the live
                                            database
                                        </Label>
                                    </div>
                                    <InputError message={errors.confirm} />
                                    <InputError message={errors.backup_id} />

                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setStep(1)}
                                        >
                                            Back
                                        </Button>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            <RotateCcw className="size-4" />
                                            Restore now
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </section>
                ) : null}

                {step === 3 ? (
                    <section className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            If anything looks wrong, restore the automatic
                            pre-restore snapshot from the backup list.
                        </p>
                        <Button type="button" variant="outline" onClick={() => setStep(1)}>
                            Restore another backup
                        </Button>
                    </section>
                ) : null}
            </div>
        </>
    );
}

RestoreWizard.layout = {
    breadcrumbs: [{ title: 'Restore wizard', href: edit() }],
};
