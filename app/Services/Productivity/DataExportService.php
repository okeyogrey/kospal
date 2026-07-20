<?php

namespace App\Services\Productivity;

use App\Enums\ImportEntity;
use App\Enums\SpreadsheetFormat;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use App\Support\Productivity\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportService
{
    public function __construct(
        protected SpreadsheetWriter $writer,
        protected AuditLogger $audit,
    ) {}

    public function export(
        Business $business,
        User $actor,
        ImportEntity $entity,
        SpreadsheetFormat $format,
    ): StreamedResponse {
        [$headers, $rows] = match ($entity) {
            ImportEntity::Products => $this->productRows($business),
            ImportEntity::Customers => $this->customerRows($business),
        };

        $filename = sprintf(
            'kospal-%s-%s.%s',
            $entity->value,
            now()->format('Y-m-d'),
            $format->extension(),
        );

        $this->audit->log(
            action: 'productivity.exported',
            metadata: [
                'entity' => $entity->value,
                'format' => $format->value,
                'row_count' => count($rows),
            ],
            actor: $actor,
            businessId: $business->id,
        );

        return response()->streamDownload(function () use ($format, $headers, $rows): void {
            $this->writer->stream($format, $headers, $rows);
        }, $filename, [
            'Content-Type' => $format->mimeType(),
        ]);
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    private function productRows(Business $business): array
    {
        $headers = [
            'name',
            'sku',
            'barcode',
            'category',
            'description',
            'cost_price',
            'selling_price',
            'min_selling_price',
            'is_negotiable',
            'reorder_level',
            'is_active',
        ];

        $products = Product::query()
            ->forBusiness($business)
            ->with('category:id,name')
            ->orderBy('name')
            ->get();

        $rows = $products->map(function (Product $product) use ($business): array {
            return [
                $product->name,
                $product->sku,
                $product->barcode,
                $product->category?->name,
                $product->description,
                Money::fromMinor($product->cost_price, $business->currency),
                Money::fromMinor($product->selling_price, $business->currency),
                Money::fromMinor($product->min_selling_price, $business->currency),
                $product->is_negotiable ? 'yes' : 'no',
                $product->reorder_level,
                $product->is_active ? 'yes' : 'no',
            ];
        })->all();

        return [$headers, $rows];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    private function customerRows(Business $business): array
    {
        $headers = [
            'name',
            'phone',
            'email',
            'address',
            'notes',
            'is_active',
            'credit_enabled',
            'credit_limit',
            'payment_terms_days',
        ];

        $customers = Customer::query()
            ->forBusiness($business)
            ->orderBy('name')
            ->get();

        $rows = $customers->map(function (Customer $customer) use ($business): array {
            return [
                $customer->name,
                $customer->phone,
                $customer->email,
                $customer->address,
                $customer->notes,
                $customer->is_active ? 'yes' : 'no',
                $customer->credit_enabled ? 'yes' : 'no',
                $customer->credit_limit !== null
                    ? Money::fromMinor($customer->credit_limit, $business->currency)
                    : null,
                $customer->payment_terms_days,
            ];
        })->all();

        return [$headers, $rows];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|float|null>>}
     */
    public function template(ImportEntity $entity): array
    {
        $headers = $entity->allColumns();
        $sample = match ($entity) {
            ImportEntity::Products => [
                'Sample product',
                'SKU-001',
                '1234567890',
                'General',
                'Optional description',
                '100.00',
                '150.00',
                '120.00',
                'yes',
                '5',
                'yes',
            ],
            ImportEntity::Customers => [
                'Jane Customer',
                '+254700000000',
                'jane@example.com',
                'Nairobi',
                'Preferred buyer',
                'yes',
                'no',
                '',
                '30',
            ],
        };

        return [
            'headers' => $headers,
            'rows' => [$sample],
        ];
    }

    public function downloadTemplate(
        ImportEntity $entity,
        SpreadsheetFormat $format,
    ): StreamedResponse {
        $template = $this->template($entity);
        $filename = sprintf('kospal-%s-template.%s', $entity->value, $format->extension());

        return response()->streamDownload(function () use ($format, $template): void {
            $this->writer->stream($format, $template['headers'], $template['rows']);
        }, $filename, [
            'Content-Type' => $format->mimeType(),
        ]);
    }
}
