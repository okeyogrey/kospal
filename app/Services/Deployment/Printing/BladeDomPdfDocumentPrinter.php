<?php

namespace App\Services\Deployment\Printing;

use App\Contracts\DesktopSettings;
use App\Contracts\DocumentPrinter;
use App\Models\Business;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Current printing strategy: HTML thermal receipt + DomPDF A4 invoice.
 *
 * Desktop printer/receipt preferences come from DesktopSettings without
 * changing SaleController.
 */
class BladeDomPdfDocumentPrinter implements DocumentPrinter
{
    public function __construct(
        protected DesktopSettings $settings,
    ) {}

    public function receipt(Sale $sale, Business $business): Response
    {
        $sale->loadMissing([
            'branch',
            'cashier:id,name',
            'customer:id,name,phone',
            'items',
            'payments',
            'business',
        ]);

        $receiptWidth = (string) $this->settings->get('receipt_width', '80');
        if (! in_array($receiptWidth, ['58', '80'], true)) {
            $receiptWidth = '80';
        }

        $labels = trans('kospal.receipt');

        return response()
            ->view('sales.receipt', [
                'sale' => $sale,
                'business' => $business,
                'currency' => $sale->currency,
                'receiptWidth' => $receiptWidth,
                'printerName' => $this->settings->get('printer_name'),
                'labels' => is_array($labels) ? $labels : [],
                'isReprint' => request()->boolean('reprint'),
            ])
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function invoice(Sale $sale, Business $business): Response
    {
        $sale->loadMissing([
            'branch',
            'cashier:id,name',
            'customer:id,name,phone,email,address',
            'items',
            'payments',
        ]);

        $labels = trans('kospal.receipt');

        $pdf = Pdf::loadView('sales.invoice-pdf', [
            'sale' => $sale,
            'business' => $business,
            'currency' => $sale->currency,
            'labels' => is_array($labels) ? $labels : [],
        ])->setPaper('a4');

        return $pdf->download($sale->sale_number.'-invoice.pdf');
    }
}
