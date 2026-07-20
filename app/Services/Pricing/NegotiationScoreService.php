<?php

namespace App\Services\Pricing;

use App\Enums\SaleStatus;
use App\Models\SaleItem;
use App\Support\Analytics\AnalyticsFilter;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

class NegotiationScoreService
{
    public function __construct(
        protected PricingEngine $pricing,
    ) {}

    /**
     * Rank salespeople by negotiation performance for the filtered period.
     *
     * @return array{
     *     summary: array<string, mixed>,
     *     rows: list<array<string, mixed>>,
     *     chart: list<array{label: string, value: float|int}>,
     * }
     */
    public function salespersonRanking(AnalyticsFilter $filter): array
    {
        $currency = $filter->currency();

        if ($filter->branchIds() === []) {
            return [
                'summary' => $this->emptySummary($currency),
                'rows' => [],
                'chart' => [],
            ];
        }

        $aggregates = SaleItem::query()
            ->from('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('users', 'users.id', '=', 'sales.cashier_id')
            ->where('sale_items.business_id', $filter->business->id)
            ->whereIn('sales.branch_id', $filter->branchIds())
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.created_at', [$filter->dateFrom, $filter->dateTo])
            ->groupBy('sales.cashier_id', 'users.name')
            ->get([
                'sales.cashier_id',
                DB::raw("COALESCE(users.name, 'Unknown') as cashier_name"),
                DB::raw('COUNT(sale_items.id) as line_count'),
                DB::raw('COUNT(DISTINCT sales.id) as sales_count'),
                DB::raw('SUM(sale_items.quantity) as units_sold'),
                DB::raw('SUM(sale_items.line_total) as revenue_minor'),
                DB::raw('SUM(sale_items.list_unit_price * sale_items.quantity) as suggested_revenue_minor'),
                DB::raw('SUM(sale_items.profit) as profit_minor'),
                DB::raw('AVG(sale_items.margin_bps) as avg_margin_bps'),
                DB::raw('AVG(CASE WHEN sale_items.list_unit_price > 0 THEN (sale_items.unit_price * 10000.0 / sale_items.list_unit_price) ELSE 10000 END) as avg_price_ratio_bps'),
                DB::raw('SUM(CASE WHEN sale_items.negotiated_difference > 0 THEN 1 ELSE 0 END) as negotiated_line_count'),
                DB::raw('SUM(CASE WHEN sale_items.negotiated_difference > 0 THEN sale_items.negotiated_difference * sale_items.quantity ELSE 0 END) as negotiation_value_minor'),
                DB::raw('SUM(CASE WHEN sale_items.manager_approved = 1 THEN 1 ELSE 0 END) as approved_line_count'),
                DB::raw('SUM(CASE WHEN sale_items.list_unit_price > 0 AND (sale_items.unit_price * 10000 / sale_items.list_unit_price) < 8000 THEN 1 ELSE 0 END) as excessive_override_count'),
            ]);

        $ratiosByCashier = $this->priceRatiosByCashier($filter);

        $rows = $aggregates->map(function ($row) use ($currency, $ratiosByCashier) {
            $cashierId = $row->cashier_id !== null ? (int) $row->cashier_id : null;
            $lineCount = max(1, (int) $row->line_count);
            $units = max(1, (int) $row->units_sold);
            $revenue = (int) $row->revenue_minor;
            $suggestedRevenue = (int) $row->suggested_revenue_minor;
            $negotiatedLines = (int) $row->negotiated_line_count;
            $negotiationValue = (int) $row->negotiation_value_minor;
            $excessive = (int) $row->excessive_override_count;
            $avgMarginBps = (int) round((float) $row->avg_margin_bps);
            $avgPriceRatioBps = (int) round((float) $row->avg_price_ratio_bps);
            $avgSellingPrice = (int) intdiv($revenue, $units);
            $avgSuggestedPrice = $suggestedRevenue > 0
                ? (int) intdiv($suggestedRevenue, $units)
                : 0;

            $ratios = $ratiosByCashier[$cashierId ?? 0] ?? [];
            $score = $this->score(
                avgPriceRatioBps: $avgPriceRatioBps,
                avgMarginBps: $avgMarginBps,
                excessiveOverrides: $excessive,
                negotiatedLines: $negotiatedLines,
                priceRatios: $ratios,
            );

            $negotiationFrequency = $lineCount > 0
                ? round(($negotiatedLines / $lineCount) * 100, 1)
                : 0.0;

            return [
                'cashier_id' => $cashierId,
                'cashier_name' => $row->cashier_name,
                'sales_count' => (int) $row->sales_count,
                'line_count' => (int) $row->line_count,
                'average_selling_price_minor' => $avgSellingPrice,
                'average_selling_price_formatted' => Money::format($avgSellingPrice, $currency),
                'average_suggested_price_minor' => $avgSuggestedPrice,
                'average_suggested_price_formatted' => Money::format($avgSuggestedPrice, $currency),
                'average_margin_bps' => $avgMarginBps,
                'average_margin_percent' => round($avgMarginBps / 100, 2),
                'negotiation_frequency_percent' => $negotiationFrequency,
                'negotiation_value_minor' => $negotiationValue,
                'negotiation_value_formatted' => Money::format($negotiationValue, $currency),
                'excessive_overrides' => $excessive,
                'approved_lines' => (int) $row->approved_line_count,
                'profit_minor' => (int) $row->profit_minor,
                'profit_formatted' => Money::format((int) $row->profit_minor, $currency),
                'revenue_minor' => $revenue,
                'revenue_formatted' => Money::format($revenue, $currency),
                'negotiation_score' => $score,
                'price_fidelity_percent' => round($avgPriceRatioBps / 100, 2),
            ];
        })
            ->sortByDesc('negotiation_score')
            ->values()
            ->all();

        $rank = 1;
        foreach ($rows as &$row) {
            $row['rank'] = $rank++;
        }
        unset($row);

        $totalNegotiationValue = array_sum(array_column($rows, 'negotiation_value_minor'));
        $totalLines = array_sum(array_column($rows, 'line_count'));
        $totalNegotiated = array_sum(array_map(
            static fn (array $row): int => (int) round($row['line_count'] * $row['negotiation_frequency_percent'] / 100),
            $rows,
        ));
        $avgScore = $rows === []
            ? 0.0
            : round(array_sum(array_column($rows, 'negotiation_score')) / count($rows), 1);

        return [
            'summary' => [
                'salesperson_count' => count($rows),
                'average_negotiation_score' => $avgScore,
                'negotiation_value_minor' => $totalNegotiationValue,
                'negotiation_value_formatted' => Money::format($totalNegotiationValue, $currency),
                'negotiation_frequency_percent' => $totalLines > 0
                    ? round(($totalNegotiated / $totalLines) * 100, 1)
                    : 0.0,
            ],
            'rows' => $rows,
            'chart' => array_map(static fn (array $row): array => [
                'label' => $row['cashier_name'],
                'value' => $row['negotiation_score'],
            ], $rows),
        ];
    }

    /**
     * @param  list<float>  $priceRatios  unit/list ratios (0–1+)
     */
    public function score(
        int $avgPriceRatioBps,
        int $avgMarginBps,
        int $excessiveOverrides,
        int $negotiatedLines,
        array $priceRatios,
    ): float {
        $priceFidelity = max(0, min(100, $avgPriceRatioBps / 100));
        $marginScore = max(0, min(100, $avgMarginBps / 50));

        $overrideRate = $negotiatedLines > 0
            ? $excessiveOverrides / $negotiatedLines
            : ($excessiveOverrides > 0 ? 1.0 : 0.0);
        $overrideScore = max(0, 100 - ($overrideRate * 100));

        $consistency = $this->consistencyScore($priceRatios);

        $weighted = ($priceFidelity * 0.35)
            + ($marginScore * 0.30)
            + ($overrideScore * 0.20)
            + ($consistency * 0.15);

        return round($weighted, 1);
    }

    /**
     * @param  list<float>  $ratios
     */
    protected function consistencyScore(array $ratios): float
    {
        $count = count($ratios);

        if ($count < 2) {
            return 100.0;
        }

        $mean = array_sum($ratios) / $count;
        $variance = 0.0;

        foreach ($ratios as $ratio) {
            $variance += ($ratio - $mean) ** 2;
        }

        $stddev = sqrt($variance / $count);

        return max(0.0, min(100.0, 100.0 - ($stddev * 200.0)));
    }

    /**
     * @return array<int, list<float>>
     */
    protected function priceRatiosByCashier(AnalyticsFilter $filter): array
    {
        $rows = SaleItem::query()
            ->from('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.business_id', $filter->business->id)
            ->whereIn('sales.branch_id', $filter->branchIds())
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.created_at', [$filter->dateFrom, $filter->dateTo])
            ->get([
                'sales.cashier_id',
                'sale_items.unit_price',
                'sale_items.list_unit_price',
            ]);

        $grouped = [];

        foreach ($rows as $row) {
            $cashierId = (int) ($row->cashier_id ?? 0);
            $list = (int) $row->list_unit_price;
            $grouped[$cashierId][] = $list > 0
                ? ((int) $row->unit_price) / $list
                : 1.0;
        }

        return $grouped;
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptySummary(string $currency): array
    {
        return [
            'salesperson_count' => 0,
            'average_negotiation_score' => 0,
            'negotiation_value_minor' => 0,
            'negotiation_value_formatted' => Money::format(0, $currency),
            'negotiation_frequency_percent' => 0,
        ];
    }
}
