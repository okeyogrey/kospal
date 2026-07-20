import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CheckCircle2, DatabaseBackup } from 'lucide-react';
import BackupWizardController from '@/actions/App/Http/Controllers/Settings/BackupWizardController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit as editBackupWizard } from '@/routes/backup';

type BackupRow = {
    id: string;
    label: string | null;
    created_at: string;
    size_bytes: number | null;
};

type Step = 1 | 2 | 3 | 4;

export default function BackupWizard({
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
    const [step, setStep] = useState<Step>(flash?.success ? 4 : 1);

    return (
        <>
            <Head title="Backup wizard" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Backup wizard"
                    description="Schedule automatic backups and create your first snapshot."
                />

                {flash?.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Backup failed</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                {flash?.success ? (
                    <Alert>
                        <CheckCircle2 className="size-4" />
                        <AlertTitle>Backup saved</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}

                <ol className="flex flex-wrap gap-2 text-xs">
                    {[
                        { n: 1, label: 'Schedule' },
                        { n: 2, label: 'Create' },
                        { n: 3, label: 'Verify' },
                        { n: 4, label: 'Done' },
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
                        <Form
                            {...BackupWizardController.updateSettings.form()}
                            options={{ preserveScroll: true }}
                            className="space-y-4"
                            onSuccess={() => setStep(2)}
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
                                            Enable daily automatic backup
                                        </Label>
                                    </div>
                                    <InputError message={errors.auto_backup} />

                                    <div className="grid gap-2">
                                        <Label htmlFor="auto_backup_hour">
                                            Backup hour (0–23)
                                        </Label>
                                        <Input
                                            id="auto_backup_hour"
                                            name="auto_backup_hour"
                                            type="number"
                                            min={0}
                                            max={23}
                                            defaultValue={
                                                settings.auto_backup_hour
                                            }
                                            className="w-28"
                                        />
                                        <InputError
                                            message={errors.auto_backup_hour}
                                        />
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={processing || !supported}
                                    >
                                        Continue
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                ) : null}

                {step === 2 ? (
                    <section className="space-y-4">
                        <Form
                            {...BackupWizardController.create.form()}
                            options={{ preserveScroll: true }}
                            className="space-y-4"
                            onSuccess={() => setStep(3)}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="label">
                                            Backup label (optional)
                                        </Label>
                                        <Input
                                            id="label"
                                            name="label"
                                            placeholder="initial-setup"
                                            disabled={!supported}
                                        />
                                        <InputError message={errors.label} />
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setStep(1)}
                                        >
                                            Back
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing || !supported}
                                        >
                                            <DatabaseBackup className="size-4" />
                                            Create backup
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
                            {backups.length} backup file(s) available. Latest:{' '}
                            <span className="font-mono text-xs">
                                {backups[0]?.id ?? 'none yet'}
                            </span>
                        </p>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setStep(2)}
                            >
                                Back
                            </Button>
                            <Button type="button" onClick={() => setStep(4)}>
                                Finish
                            </Button>
                        </div>
                    </section>
                ) : null}

                {step === 4 ? (
                    <section className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Backup wizard complete. Use Restore wizard if you
                            need to roll back.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStep(1)}
                        >
                            Run again
                        </Button>
                    </section>
                ) : null}
            </div>
        </>
    );
}

BackupWizard.layout = {
    breadcrumbs: [{ title: 'Backup wizard', href: editBackupWizard() }],
};
