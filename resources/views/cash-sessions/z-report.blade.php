<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Z Report · {{ $session->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Courier New", Courier, monospace; color: #111; background: #fff; }
        .toolbar { display: flex; gap: 0.75rem; justify-content: center; padding: 1rem; border-bottom: 1px solid #ddd; background: #f7f7f7; }
        .toolbar button { font: inherit; padding: 0.5rem 0.9rem; border: 1px solid #222; background: #fff; cursor: pointer; }
        .receipt { max-width: 42rem; margin: 0 auto; padding: 1.5rem 1rem 2rem; }
        h1 { font-size: 1.1rem; text-align: center; margin: 0 0 0.25rem; }
        .meta { text-align: center; font-size: 0.85rem; color: #444; margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th, td { padding: 0.35rem 0; border-bottom: 1px dashed #ccc; }
        th { text-align: left; font-weight: 600; }
        td.amount { text-align: right; white-space: nowrap; }
        .section { margin-top: 1rem; }
        .section h2 { font-size: 0.9rem; margin: 0 0 0.35rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .variance { font-weight: 700; }
        .variance.nonzero { color: #b45309; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">{{ $labels['print'] ?? 'Print' }}</button>
    </div>

    <div class="receipt">
        <h1>{{ $labels['z_report'] ?? 'Z Report' }}</h1>
        <p class="meta">
            {{ $business->name }}<br>
            {{ $session->branch?->name }} · {{ $session->user?->name }}<br>
            {{ $labels['session'] ?? 'Session' }} #{{ $session->id }}
        </p>

        <table>
            <tr>
                <th>{{ $labels['opened_at'] ?? 'Opened' }}</th>
                <td class="amount">{{ $report['opened_at'] ?? '—' }}</td>
            </tr>
            <tr>
                <th>{{ $labels['closed_at'] ?? 'Closed' }}</th>
                <td class="amount">{{ $report['closed_at'] ?? '—' }}</td>
            </tr>
        </table>

        <div class="section">
            <h2>{{ $labels['cash_summary'] ?? 'Cash summary' }}</h2>
            <table>
                <tr><th>{{ $labels['opening_float'] ?? 'Opening float' }}</th><td class="amount">{{ $report['opening_float_formatted'] ?? '—' }}</td></tr>
                <tr><th>{{ $labels['cash_sales'] ?? 'Cash sales' }}</th><td class="amount">{{ \App\Support\Money\Money::format($report['cash_sales_minor'] ?? 0, $report['currency'] ?? $business->currency) }}</td></tr>
                <tr><th>{{ $labels['paid_ins'] ?? 'Paid ins' }}</th><td class="amount">{{ \App\Support\Money\Money::format($report['paid_ins_minor'] ?? 0, $report['currency'] ?? $business->currency) }}</td></tr>
                <tr><th>{{ $labels['drops'] ?? 'Cash drops' }}</th><td class="amount">{{ \App\Support\Money\Money::format($report['drops_minor'] ?? 0, $report['currency'] ?? $business->currency) }}</td></tr>
                <tr><th>{{ $labels['expected_cash'] ?? 'Expected cash' }}</th><td class="amount">{{ $report['expected_cash_formatted'] ?? '—' }}</td></tr>
                <tr><th>{{ $labels['counted_cash'] ?? 'Counted cash' }}</th><td class="amount">{{ $report['counted_cash_formatted'] ?? '—' }}</td></tr>
                <tr><th>{{ $labels['closing_float'] ?? 'Closing float' }}</th><td class="amount">{{ $report['closing_float_left_formatted'] ?? '—' }}</td></tr>
                <tr>
                    <th class="variance {{ ($report['variance_minor'] ?? 0) !== 0 ? 'nonzero' : '' }}">{{ $labels['variance'] ?? 'Variance' }}</th>
                    <td class="amount variance {{ ($report['variance_minor'] ?? 0) !== 0 ? 'nonzero' : '' }}">{{ $report['variance_formatted'] ?? '—' }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2>{{ $labels['sales_summary'] ?? 'Sales summary' }}</h2>
            <table>
                <tr><th>{{ $labels['sale_count'] ?? 'Sales' }}</th><td class="amount">{{ $report['sales']['sale_count'] ?? 0 }}</td></tr>
                <tr><th>{{ $labels['subtotal'] ?? 'Subtotal' }}</th><td class="amount">{{ \App\Support\Money\Money::format($report['sales']['subtotal_minor'] ?? 0, $report['currency'] ?? $business->currency) }}</td></tr>
                <tr><th>{{ $labels['discounts'] ?? 'Discounts' }}</th><td class="amount">{{ \App\Support\Money\Money::format($report['sales']['discount_minor'] ?? 0, $report['currency'] ?? $business->currency) }}</td></tr>
                <tr><th>{{ $labels['total'] ?? 'Total' }}</th><td class="amount">{{ \App\Support\Money\Money::format($report['sales']['total_minor'] ?? 0, $report['currency'] ?? $business->currency) }}</td></tr>
            </table>
        </div>

        @if (! empty($report['payment_breakdown']))
            <div class="section">
                <h2>{{ $labels['payment_breakdown'] ?? 'Payment breakdown' }}</h2>
                <table>
                    @foreach ($report['payment_breakdown'] as $row)
                        <tr>
                            <th>{{ $row['method_label'] ?? $row['method'] }}</th>
                            <td class="amount">{{ $row['total_formatted'] ?? '—' }} ({{ $row['payment_count'] ?? 0 }})</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif

        @if (($report['voided']['count'] ?? 0) > 0 || ($report['returns']['count'] ?? 0) > 0)
            <div class="section">
                <h2>{{ $labels['adjustments'] ?? 'Adjustments' }}</h2>
                <table>
                    <tr><th>{{ $labels['voided_sales'] ?? 'Voided sales' }}</th><td class="amount">{{ $report['voided']['count'] ?? 0 }}</td></tr>
                    <tr><th>{{ $labels['returns'] ?? 'Returns' }}</th><td class="amount">{{ $report['returns']['count'] ?? 0 }}</td></tr>
                </table>
            </div>
        @endif

        @if (! empty($session->variance_reason))
            <div class="section">
                <h2>{{ $labels['variance_reason'] ?? 'Variance reason' }}</h2>
                <p>{{ $session->variance_reason }}</p>
                @if ($session->varianceApprover)
                    <p class="meta">{{ $labels['approved_by'] ?? 'Approved by' }}: {{ $session->varianceApprover->name }}</p>
                @endif
            </div>
        @endif
    </div>
</body>
</html>
