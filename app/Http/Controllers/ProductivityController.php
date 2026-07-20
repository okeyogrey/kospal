<?php

namespace App\Http\Controllers;

use App\Contracts\FeatureFlagService;
use App\Enums\ImportEntity;
use App\Enums\SpreadsheetFormat;
use App\Http\Requests\Productivity\ImportDataRequest;
use App\Services\Productivity\DataExportService;
use App\Services\Productivity\DataImportService;
use App\Support\FeatureFlags\Features;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductivityController extends Controller
{
    public function index(
        TenantContext $tenant,
        FeatureFlagService $features,
    ): Response {
        $this->authorize('viewAny', ImportEntity::class);

        $business = $tenant->business();
        abort_unless($business, 403);

        $role = $tenant->role();

        $entities = collect(ImportEntity::cases())->map(fn (ImportEntity $entity) => [
            'key' => $entity->value,
            'label' => $entity->label(),
            'columns' => $entity->allColumns(),
            'can_import_csv' => $tenant->user()?->can('import', [$entity, SpreadsheetFormat::Csv]) ?? false,
            'can_import_excel' => $tenant->user()?->can('import', [$entity, SpreadsheetFormat::Xlsx]) ?? false,
            'can_export_csv' => $tenant->user()?->can('export', [$entity, SpreadsheetFormat::Csv]) ?? false,
            'can_export_excel' => $tenant->user()?->can('export', [$entity, SpreadsheetFormat::Xlsx]) ?? false,
        ]);

        return Inertia::render('productivity/index', [
            'entities' => $entities,
            'features' => [
                'csv_import' => $features->hasFeature($business, Features::CSV_IMPORT),
                'excel_import' => $features->hasFeature($business, Features::EXCEL_IMPORT),
                'csv_export' => $features->hasFeature($business, Features::CSV_EXPORT),
                'excel_export' => $features->hasFeature($business, Features::EXCEL_EXPORT),
                'pdf_reports' => $features->hasFeature($business, Features::PDF_REPORTS),
            ],
            'plan' => $business->plan->value,
        ]);
    }

    public function import(
        ImportDataRequest $request,
        TenantContext $tenant,
        DataImportService $imports,
    ): RedirectResponse {
        $business = $tenant->business();
        $user = $tenant->user();
        abort_unless($business && $user, 403);

        $entity = ImportEntity::from($request->validated('entity'));
        $format = $request->validated('format');

        $result = $imports->import(
            $business,
            $user,
            $entity,
            $request->file('file'),
            $format,
            (bool) $request->boolean('update_existing'),
        );

        $message = sprintf(
            'Import finished: %d created, %d updated, %d skipped.',
            $result['created'],
            $result['updated'],
            $result['skipped'],
        );

        if ($result['errors'] !== []) {
            return back()
                ->with('success', $message)
                ->with('import_errors', array_slice($result['errors'], 0, 10));
        }

        return back()->with('success', $message);
    }

    public function export(
        Request $request,
        string $entity,
        string $format,
        TenantContext $tenant,
        DataExportService $exports,
    ): StreamedResponse {
        $importEntity = ImportEntity::tryFrom($entity);
        $spreadsheetFormat = SpreadsheetFormat::tryFrom($format);
        abort_unless($importEntity !== null && $spreadsheetFormat !== null, 404);

        $this->authorize('export', [$importEntity, $spreadsheetFormat]);

        $business = $tenant->business();
        $user = $tenant->user();
        abort_unless($business && $user, 403);

        return $exports->export($business, $user, $importEntity, $spreadsheetFormat);
    }

    public function template(
        string $entity,
        string $format,
        TenantContext $tenant,
        DataExportService $exports,
    ): StreamedResponse {
        $importEntity = ImportEntity::tryFrom($entity);
        $spreadsheetFormat = SpreadsheetFormat::tryFrom($format);
        abort_unless($importEntity !== null && $spreadsheetFormat !== null, 404);

        $this->authorize('viewAny', ImportEntity::class);

        return $exports->downloadTemplate($importEntity, $spreadsheetFormat);
    }
}
