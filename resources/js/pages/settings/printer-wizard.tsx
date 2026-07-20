import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CheckCircle2, Printer } from 'lucide-react';
import PrinterWizardController from '@/actions/App/Http/Controllers/Settings/PrinterWizardController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit as editPrinterWizard } from '@/routes/printer';

type Step = 1 | 2 | 3 | 4;

export default function PrinterWizard({
    settings,
    test_receipt_url,
}: {
    settings: { printer_name: string; receipt_width: string };
    test_receipt_url: string;
}) {
    const flash = usePage().props.flash as
        | { success?: string }
        | undefined;
    const [step, setStep] = useState<Step>(flash?.success ? 4 : 1);
    const [width, setWidth] = useState(settings.receipt_width);
    const [printerName, setPrinterName] = useState(settings.printer_name);

    return (
        <>
            <Head title="Printer wizard" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Printer wizard"
                    description="Configure thermal receipt width, name your printer, and test printing."
                />

                {flash?.success ? (
                    <Alert>
                        <CheckCircle2 className="size-4" />
                        <AlertTitle>Saved</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}

                <ol className="flex flex-wrap gap-2 text-xs">
                    {[
                        { n: 1, label: 'Paper' },
                        { n: 2, label: 'Printer' },
                        { n: 3, label: 'Test' },
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
                        <div className="grid gap-2">
                            <Label htmlFor="wizard_width">Receipt width</Label>
                            <select
                                id="wizard_width"
                                value={width}
                                onChange={(event) =>
                                    setWidth(event.target.value)
                                }
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            >
                                <option value="58">58 mm</option>
                                <option value="80">80 mm</option>
                            </select>
                        </div>
                        <Button type="button" onClick={() => setStep(2)}>
                            Continue
                        </Button>
                    </section>
                ) : null}

                {step === 2 ? (
                    <section className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="wizard_printer_name">
                                OS printer name
                            </Label>
                            <Input
                                id="wizard_printer_name"
                                value={printerName}
                                onChange={(event) =>
                                    setPrinterName(event.target.value)
                                }
                                placeholder="EPSON TM-T20"
                            />
                        </div>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setStep(1)}
                            >
                                Back
                            </Button>
                            <Button type="button" onClick={() => setStep(3)}>
                                Continue
                            </Button>
                        </div>
                    </section>
                ) : null}

                {step === 3 ? (
                    <section className="space-y-4">
                        <Alert>
                            <Printer className="size-4" />
                            <AlertTitle>Test print</AlertTitle>
                            <AlertDescription>
                                Complete a sale and open the receipt preview.
                                Select{' '}
                                <strong>
                                    {printerName || 'your thermal printer'}
                                </strong>{' '}
                                in the browser print dialog.
                            </AlertDescription>
                        </Alert>
                        <Button variant="outline" asChild>
                            <Link href={test_receipt_url}>Open sales</Link>
                        </Button>
                        <Form
                            {...PrinterWizardController.update.form()}
                            options={{ preserveScroll: true }}
                            className="space-y-4"
                            onSuccess={() => setStep(4)}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="printer_name"
                                        value={printerName}
                                    />
                                    <input
                                        type="hidden"
                                        name="receipt_width"
                                        value={width}
                                    />
                                    <InputError
                                        message={errors.printer_name}
                                    />
                                    <InputError
                                        message={errors.receipt_width}
                                    />
                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setStep(2)}
                                        >
                                            Back
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save and finish
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </section>
                ) : null}

                {step === 4 ? (
                    <section className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Printer wizard complete. Receipt width: {width} mm.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStep(1)}
                        >
                            Reconfigure
                        </Button>
                    </section>
                ) : null}
            </div>
        </>
    );
}

PrinterWizard.layout = {
    breadcrumbs: [{ title: 'Printer wizard', href: editPrinterWizard() }],
};
