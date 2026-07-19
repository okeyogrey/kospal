<?php

namespace App\Http\Controllers;

use App\Enums\ReportType;
use App\Models\Branch;
use App\Services\Analytics\DashboardMetricsService;
use App\Support\Analytics\AnalyticsFilter;
use App\Support\Tenancy\ResolvesTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenant,
        ResolvesTenant $resolver,
        DashboardMetricsService $metrics,
    ): Response {
        $business = $tenant->business();
        $user = $tenant->user();
        $membership = $tenant->membership();
        abort_unless($business && $user && $membership, 403);

        $allowedBranches = $resolver->allowedBranches($user, $membership, $business);
        $allowedBranchIds = $allowedBranches->pluck('id')->map(fn ($id) => (int) $id)->all();

        $filter = AnalyticsFilter::fromRequest(
            $request,
            $business,
            $allowedBranchIds,
            defaultFrom: now()->startOfMonth()->startOfDay(),
            defaultTo: now()->endOfDay(),
        );

        $payload = $metrics->build($filter);

        return Inertia::render('dashboard', [
            'metrics' => $payload['metrics'],
            'recent_sales' => $payload['recent_sales'],
            'top_products' => $payload['top_products'],
            'sales_trend' => $payload['sales_trend'],
            'payment_breakdown' => $payload['payment_breakdown'],
            'loss_breakdown' => $payload['loss_breakdown'],
            'branches' => $allowedBranches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
            ])->values(),
            'filters' => $filter->toArray(),
            'currency' => $business->currency,
            'permissions' => [
                'view_reports' => $user->can('viewAny', ReportType::class),
            ],
        ]);
    }
}
