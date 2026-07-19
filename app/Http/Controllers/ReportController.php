<?php

namespace App\Http\Controllers;

use App\Enums\ReportType;
use App\Models\Branch;
use App\Services\Analytics\ReportQueryService;
use App\Support\Analytics\AnalyticsFilter;
use App\Support\Audit\AuditLogger;
use App\Support\Plans\PlanLimitChecker;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(
        TenantContext $tenant,
    ): Response {
        $this->authorize('viewAny', ReportType::class);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $plan = $business->plan;
        $reports = collect(ReportType::cases())->map(fn (ReportType $type) => [
            'key' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'group' => $type->group(),
            'min_plan' => $type->minPlan()->value,
            'available' => $type->isAvailableOn($plan),
            'supports_csv' => $type->supportsCsvExport() && $plan->allowsCsvExport() && $type->isAvailableOn($plan),
        ])->groupBy('group');

        return Inertia::render('reports/index', [
            'reports' => $reports,
            'plan' => $plan->value,
            'features' => [
                'advanced_reports' => $plan->allowsAdvancedReports(),
                'csv_export' => $plan->allowsCsvExport(),
                'consolidated_reports' => $plan->allowsConsolidatedReports(),
                'audit_logs' => $plan->allowsAuditLogs(),
                'enhanced_exports' => $plan->allowsEnhancedExports(),
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

        $available = $type->isAvailableOn($business->plan);
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
        PlanLimitChecker $limits,
        AuditLogger $audit,
    ): StreamedResponse {
        $type = ReportType::tryFrom($report);
        abort_unless($type !== null, 404);

        $this->authorize('export', $type);

        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $limits->assertHasFeature($business, 'csv_export');
        abort_unless($type->isAvailableOn($business->plan), 422, 'This plan does not include the requested report.');
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
}
