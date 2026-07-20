<?php

namespace App\Contracts;

use App\Models\Business;
use App\Models\Sale;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders sale documents for the current deployment.
 *
 * Web: HTML receipt + DomPDF invoice.
 * Desktop may later bridge to ESC/POS or OS print dialogs without
 * changing SaleController business flow.
 */
interface DocumentPrinter
{
    public function receipt(Sale $sale, Business $business): Response;

    public function invoice(Sale $sale, Business $business): Response;
}
