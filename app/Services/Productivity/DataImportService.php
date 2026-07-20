<?php

namespace App\Services\Productivity;

use App\Enums\ImportEntity;
use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\ProductService;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use App\Support\Productivity\SpreadsheetReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DataImportService
{
    public function __construct(
        protected SpreadsheetReader $reader,
        protected ProductService $products,
        protected CustomerService $customers,
        protected AuditLogger $audit,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(
        Business $business,
        User $actor,
        ImportEntity $entity,
        UploadedFile $file,
        string $format,
        bool $updateExisting = false,
    ): array {
        $spreadsheetFormat = \App\Enums\SpreadsheetFormat::tryFrom($format);
        if ($spreadsheetFormat === null) {
            throw ValidationException::withMessages([
                'format' => 'Unsupported import format.',
            ]);
        }

        $parsed = $this->reader->read($file, $spreadsheetFormat);
        $missing = array_values(array_diff(
            $entity->requiredColumns(),
            $parsed['headers'],
        ));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'Missing required columns: '.implode(', ', $missing).'.',
            ]);
        }

        return match ($entity) {
            ImportEntity::Products => $this->importProducts(
                $business,
                $actor,
                $parsed['rows'],
                $updateExisting,
            ),
            ImportEntity::Customers => $this->importCustomers(
                $business,
                $actor,
                $parsed['rows'],
                $updateExisting,
            ),
        };
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    private function importProducts(
        Business $business,
        User $actor,
        array $rows,
        bool $updateExisting,
    ): array {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $categories = Category::query()
            ->forBusiness($business)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id]);

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            try {
                $name = trim((string) ($row['name'] ?? ''));
                $sku = trim((string) ($row['sku'] ?? ''));

                if ($name === '' || $sku === '') {
                    throw new \InvalidArgumentException('Name and SKU are required.');
                }

                $existing = Product::query()
                    ->forBusiness($business)
                    ->where('sku', $sku)
                    ->first();

                $payload = [
                    'name' => $name,
                    'sku' => $sku,
                    'barcode' => $this->nullableString($row['barcode'] ?? null),
                    'description' => $this->nullableString($row['description'] ?? null),
                    'cost_price' => Money::toMinor((string) $row['cost_price'], $business->currency),
                    'selling_price' => Money::toMinor((string) $row['selling_price'], $business->currency),
                    'min_selling_price' => isset($row['min_selling_price']) && $row['min_selling_price'] !== null && $row['min_selling_price'] !== ''
                        ? Money::toMinor((string) $row['min_selling_price'], $business->currency)
                        : null,
                    'is_negotiable' => $this->parseBoolean($row['is_negotiable'] ?? null, true),
                    'reorder_level' => (int) ($row['reorder_level'] ?? 0),
                    'is_active' => $this->parseBoolean($row['is_active'] ?? null, true),
                ];

                $categoryName = strtolower(trim((string) ($row['category'] ?? '')));
                if ($categoryName !== '') {
                    $payload['category_id'] = $categories[$categoryName] ?? null;
                }

                if ($existing !== null && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                DB::transaction(function () use (
                    $business,
                    $actor,
                    $existing,
                    $payload,
                    &$stats,
                ): void {
                    if ($existing !== null) {
                        $this->products->update($existing, $payload, $actor);
                        $stats['updated']++;
                    } else {
                        if ($payload['min_selling_price'] === null) {
                            $payload['min_selling_price'] = $payload['cost_price'];
                        }

                        $this->products->create($business, $payload, $actor);
                        $stats['created']++;
                    }
                });
            } catch (\Throwable $e) {
                $stats['errors'][] = "Row {$line}: {$e->getMessage()}";
            }
        }

        $this->audit->log(
            action: 'productivity.imported',
            metadata: [
                'entity' => ImportEntity::Products->value,
                'created' => $stats['created'],
                'updated' => $stats['updated'],
                'skipped' => $stats['skipped'],
                'errors' => count($stats['errors']),
            ],
            actor: $actor,
            businessId: $business->id,
        );

        return $stats;
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    private function importCustomers(
        Business $business,
        User $actor,
        array $rows,
        bool $updateExisting,
    ): array {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            try {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    throw new \InvalidArgumentException('Name is required.');
                }

                $phone = $this->nullableString($row['phone'] ?? null);
                $email = $this->nullableString($row['email'] ?? null);

                $existing = Customer::query()
                    ->forBusiness($business)
                    ->when($phone !== null, fn ($q) => $q->where('phone', $phone))
                    ->when($phone === null && $email !== null, fn ($q) => $q->where('email', $email))
                    ->when($phone === null && $email === null, fn ($q) => $q->where('name', $name))
                    ->first();

                $payload = [
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'address' => $this->nullableString($row['address'] ?? null),
                    'notes' => $this->nullableString($row['notes'] ?? null),
                    'is_active' => $this->parseBoolean($row['is_active'] ?? null, true),
                    'credit_enabled' => $this->parseBoolean($row['credit_enabled'] ?? null, false),
                    'credit_limit' => isset($row['credit_limit']) && $row['credit_limit'] !== null && $row['credit_limit'] !== ''
                        ? Money::toMinor((string) $row['credit_limit'], $business->currency)
                        : null,
                    'payment_terms_days' => isset($row['payment_terms_days']) && $row['payment_terms_days'] !== null && $row['payment_terms_days'] !== ''
                        ? (int) $row['payment_terms_days']
                        : null,
                ];

                if ($existing !== null && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($existing !== null) {
                    $this->customers->update($existing, $payload, $actor);
                    $stats['updated']++;
                } else {
                    $this->customers->create($business, $payload, $actor);
                    $stats['created']++;
                }
            } catch (\Throwable $e) {
                $stats['errors'][] = "Row {$line}: {$e->getMessage()}";
            }
        }

        $this->audit->log(
            action: 'productivity.imported',
            metadata: [
                'entity' => ImportEntity::Customers->value,
                'created' => $stats['created'],
                'updated' => $stats['updated'],
                'skipped' => $stats['skipped'],
                'errors' => count($stats['errors']),
            ],
            actor: $actor,
            businessId: $business->id,
        );

        return $stats;
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function parseBoolean(?string $value, bool $default): bool
    {
        if ($value === null || trim($value) === '') {
            return $default;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, ['1', 'true', 'yes', 'y'], true);
    }
}
