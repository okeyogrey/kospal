import { Form, Head, usePage } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import PrinterSettingsController from '@/actions/App/Http/Controllers/Settings/PrinterSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/printer';

export default function PrinterSettings({
    settings,
}: {
    settings: { printer_name: string; receipt_width: string };
}) {
    const flash = usePage().props.flash as
        | { success?: string; error?: string }
        | undefined;

    return (
        <>
            <Head title="Receipt printer" />

            <h1 className="sr-only">Receipt printer configuration</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Receipt printer"
                    description="Choose the preferred printer name and thermal paper width for this till."
                />

                {flash?.success ? (
                    <Alert>
                        <AlertTitle>Saved</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}

                <Alert>
                    <Printer className="size-4" />
                    <AlertTitle>How printing works</AlertTitle>
                    <AlertDescription>
                        Receipts open as print-ready HTML. Set the OS printer
                        name so staff know which device to select. ESC/POS
                        bridging can use this name later without changing sales
                        flows.
                    </AlertDescription>
                </Alert>

                <Form
                    {...PrinterSettingsController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="printer_name">
                                    Printer name
                                </Label>
                                <Input
                                    id="printer_name"
                                    name="printer_name"
                                    defaultValue={settings.printer_name}
                                    placeholder="EPSON TM-T20"
                                />
                                <InputError message={errors.printer_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="receipt_width">
                                    Receipt width
                                </Label>
                                <select
                                    id="receipt_width"
                                    name="receipt_width"
                                    defaultValue={settings.receipt_width}
                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                >
                                    <option value="58">58 mm</option>
                                    <option value="80">80 mm</option>
                                </select>
                                <InputError message={errors.receipt_width} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                Save printer settings
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

PrinterSettings.layout = {
    breadcrumbs: [{ title: 'Printer', href: edit() }],
};
