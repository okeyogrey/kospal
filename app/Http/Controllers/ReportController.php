<?php

namespace App\Http\Controllers;

use App\Contracts\FeatureFlagService;
use App\Enums\ReportType;
use App\Models\Branch;
use App\Services\Analytics\ReportQueryService;
use App\Support\Analytics\AnalyticsFilter;
use App\Support\Audit\AuditLogger;
use App\Support\FeatureFlags\Features;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(
        TenantContext $tenant,
        FeatureFlagService $features,
    ): Response {
        $this->authorize('viewAny', ReportType::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $reports = collect(ReportType::cases())->map(fn (ReportType $type) => [
            'key' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'group' => $type->group(),
            'min_plan' => $type->minPlan()->value,
            'available' => $type->isAvailableFor($business, $features),
                'supports_csv' => $type->supportsCsvExport()
                    && $features->hasFeature($business, Features::CSV_EXPORT)
                    && $type->isAvailableFor($business, $features),
                'supports_pdf' => $type->supportsPdfExport()
                    && $features->hasFeature($business, Features::PDF_REPORTS)
                    && $type->isAvailableFor($business, $features),
        ])->groupBy('group');

        return Inertia::render('reports/index', [
            'reports' => $reports,
            'plan' => $business->plan->value,
            'features' => [
                'advanced_reports' => $features->hasFeature($business, Features::ADVANCED_REPORTS),
                'csv_export' => $features->hasFeature($business, Features::CSV_EXPORT),
                'pdf_reports' => $features->hasFeature($business, Features::PDF_REPORTS),
                'consolidated_reports' => $features->hasFeature($business, Features::CONSOLIDATED_REPORTS),
                'audit_logs' => $features->hasFeature($business, Features::AUDIT_LOGS),
                'enhanced_exports' => $business->plan->allowsEnhancedExports(),
            ],
            'currency' => $business->currency,
        ]);
    }

    public function show(
        Request $request,
        string $report,
        TenantContext $tenant,
        ResolvesTenant $resolver,
        ReportQueryService $queries,
        FeatureFlagService $features,
    ): Response {
        $this->authorize('viewAny', ReportType::class);

        $type = ReportType::tryFrom($report);
        abort_unless($type !== null, 404);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);
        $allowedBranchIds = $allowedBranches->pluck('id')->map(fn ($id) => (int) $id)->all();

        $available = $type->isAvailableFor($business, $features);
        $filter = AnalyticsFilter::fromRequest($request, $business, $allowedBranchIds);

        $payload = $available
            ? $queries->run($type, $filter)
            : ['rows' => [], 'chart' => [], 'summary' => []];

        return Inertia::render('reports/show', [
            'report' => [
                'key' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'group' => $type->group(),
                'min_plan' => $type->minPlan()->value,
                'available' => $available,
                'supports_csv' => $type->supportsCsvExport(),
                'supports_pdf' => $type->supportsPdfExport(),
                'supports_grain' => in_array($type, [
                    ReportType::SalesTrends,
                    ReportType::ExpenseTrend,
                ], true),
            ],
            'summary' => $payload['summary'] ?? [],
            'rows' => $payload['rows'] ?? [],
            'chart' => $payload['chart'] ?? [],
            'meta' => $payload['meta'] ?? null,
            'branches' => $allowedBranches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
            ])->values(),
            'filters' => $filter->toArray(),
            'currency' => $business->currency,
            'permissions' => [
                'export' => $user->can('export', $type),
                'export_pdf' => $user->can('exportPdf', $type),
                'enhanced_exports' => $business->plan->allowsEnhancedExports(),
            ],
            'upgrade' => $available ? null : [
                'required_plan' => $type->minPlan()->value,
                'current_plan' => $business->plan->value,
                'message' => sprintf(
                    'The %s report requires the %s plan or higher.',
                    $type->label(),
                    $type->minPlan()->config()['name'],
                ),
            ],
        ]);
    }

    public function export(
        Request $request,
        string $report,
        TenantContext $tenant,
        ResolvesTenant $resolver,
        ReportQueryService $queries,
        FeatureFlagService $features,
        AuditLogger $audit,
    ): StreamedResponse {
        $type = ReportType::tryFrom($report);
        abort_unless($type !== null, 404);

        $this->authorize('export', $type);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $features->assertHasFeature($business, Features::CSV_EXPORT);
        abort_unless($type->isAvailableFor($business, $features), 422, 'This plan does not include the requested report.');
        abort_unless($type->supportsCsvExport(), 422, 'This report cannot be exported.');

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);
        $allowedBranchIds = $allowedBranches->pluck('id')->map(fn ($id) => (int) $id)->all();
        $filter = AnalyticsFilter::fromRequest($request, $business, $allowedBranchIds);

        $csvRows = $queries->csvRows($type, $filter);
        $filename = sprintf(
            'kospal-%s-%s-to-%s.csv',
            $type->value,
            $filter->dateFrom->toDateString(),
            $filter->dateTo->toDateString(),
        );

        $audit->log('report.exported', metadata: [
            'report' => $type->value,
            'filters' => $filter->toArray(),
            'row_count' => max(0, count($csvRows) - 1),
        ]);

        return response()->streamDownload(function () use ($csvRows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            foreach ($csvRows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(
        Request $request,
        string $report,
        TenantContext $tenant,
        ResolvesTenant $resolver,
        ReportQueryService $queries,
        FeatureFlagService $features,
        AuditLogger $audit,
    ) {
        $type = ReportType::tryFrom($report);
        abort_unless($type !== null, 404);

        $this->authorize('exportPdf', $type);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $features->assertHasFeature($business, Features::PDF_REPORTS);
        abort_unless($type->isAvailableFor($business, $features), 422, 'This plan does not include the requested report.');
        abort_unless($type->supportsPdfExport(), 422, 'This report cannot be exported as PDF.');

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);
        $allowedBranchIds = $allowedBranches->pluck('id')->map(fn ($id) => (int) $id)->all();
        $filter = AnalyticsFilter::fromRequest($request, $business, $allowedBranchIds);
        $payload = $queries->run($type, $filter);
        $csvRows = $queries->csvRows($type, $filter);
        $headers = $csvRows[0] ?? [];
        $rows = array_slice($csvRows, 1);

        $filename = sprintf(
            'kospal-%s-%s-to-%s.pdf',
            $type->value,
            $filter->dateFrom->toDateString(),
            $filter->dateTo->toDateString(),
        );

        $audit->log('report.exported_pdf', metadata: [
            'report' => $type->value,
            'filters' => $filter->toArray(),
            'row_count' => count($rows),
        ]);

        return Pdf::loadView('reports.export-pdf', [
            'business' => $business,
            'report' => $type,
            'filter' => $filter,
            'summary' => $payload['summary'] ?? [],
            'headers' => $headers,
            'rows' => $rows,
            'generatedAt' => now(),
        ])->download($filename);
    }
}
