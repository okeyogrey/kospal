<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->sale_number }} — {{ $labels['invoice'] ?? 'Invoice' }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111;
            margin: 24px;
        }

        h1 {
            font-size: 22px;
            margin: 0 0 4px;
        }

        .muted { color: #555; }
        .header {
            width: 100%;
            margin-bottom: 24px;
        }

        .header td { vertical-align: top; }
        .right { text-align: right; }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }

        table.items th,
        table.items td {
            border-bottom: 1px solid #ddd;
            padding: 8px 6px;
            text-align: left;
        }

        table.items th {
            background: #f3f3f3;
            font-weight: 700;
        }

        .qty, .money { text-align: right; white-space: nowrap; }

        .totals {
            width: 45%;
            margin-left: auto;
            margin-top: 16px;
            border-collapse: collapse;
        }

        .totals td {
            padding: 6px 0;
        }

        .totals td:last-child {
            text-align: right;
            font-weight: 700;
        }

        .badge {
            display: inline-block;
            border: 1px solid #111;
            padding: 2px 8px;
            text-transform: uppercase;
            font-size: 10px;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>{{ $business->name }}</h1>
                <div class="muted">{{ $sale->branch?->name }}</div>
                @if ($sale->branch?->address)
                    <div class="muted">{{ $sale->branch->address }}</div>
                @endif
                @if ($sale->status->value === 'voided')
                    <div class="badge">{{ $labels['voided'] ?? 'Voided' }}</div>
                @endif
            </td>
            <td class="right">
                <strong>{{ $labels['invoice'] ?? 'Invoice / Receipt' }}</strong><br>
                {{ $sale->sale_number }}<br>
                {{ $sale->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
            </td>
        </tr>
    </table>

    <table class="header">
        <tr>
            <td>
                <strong>{{ $labels['bill_to'] ?? 'Bill to' }}</strong><br>
                {{ $sale->customer?->name ?? $sale->customer_name ?? ($labels['walk_in_customer'] ?? 'Walk-in customer') }}<br>
                @if ($sale->customer?->phone)
                    <span class="muted">{{ $sale->customer->phone }}</span><br>
                @endif
                @if ($sale->customer?->email)
                    <span class="muted">{{ $sale->customer->email }}</span><br>
                @endif
                @if ($sale->customer?->address)
                    <span class="muted">{{ $sale->customer->address }}</span>
                @endif
            </td>
            <td class="right">
                <strong>{{ $labels['cashier'] ?? 'Cashier' }}</strong><br>
                {{ $sale->cashier?->name }}<br>
                <strong>{{ $labels['payment'] ?? 'Payment' }}</strong><br>
                {{ $sale->payment_method?->label() }}
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>{{ $labels['item'] ?? 'Item' }}</th>
                <th>{{ $labels['sku'] ?? 'SKU' }}</th>
                <th class="qty">{{ $labels['qty'] ?? 'Qty' }}</th>
                <th class="money">{{ $labels['unit_price'] ?? 'Unit price' }}</th>
                <th class="money">{{ $labels['line_total'] ?? 'Line total' }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->sku }}</td>
                    <td class="qty">{{ $item->quantity }}</td>
                    <td class="money">{{ \App\Support\Money\Money::format($item->unit_price, $currency) }}</td>
                    <td class="money">{{ \App\Support\Money\Money::format($item->line_total, $currency) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ $labels['subtotal'] ?? 'Subtotal' }}</td>
            <td>{{ \App\Support\Money\Money::format($sale->subtotal, $currency) }}</td>
        </tr>
        @if ($sale->discount_amount > 0)
            <tr>
                <td>{{ $labels['discount'] ?? 'Discount' }}</td>
                <td>-{{ \App\Support\Money\Money::format($sale->discount_amount, $currency) }}</td>
            </tr>
        @endif
        <tr>
            <td>{{ $labels['total'] ?? 'Total' }}</td>
            <td>{{ \App\Support\Money\Money::format($sale->total, $currency) }}</td>
        </tr>
        @foreach ($sale->payments->filter(fn ($payment) => $payment->amount > 0) as $payment)
            <tr>
                <td>{{ $labels['payment'] ?? 'Payment' }} ({{ $payment->method->label() }})</td>
                <td>{{ \App\Support\Money\Money::format($payment->amount, $currency) }}</td>
            </tr>
        @endforeach
        @if ($sale->cash_tendered > 0)
            <tr>
                <td>{{ $labels['cash_tendered'] ?? 'Cash tendered' }}</td>
                <td>{{ \App\Support\Money\Money::format($sale->cash_tendered, $currency) }}</td>
            </tr>
            <tr>
                <td>{{ $labels['change'] ?? 'Change' }}</td>
                <td>{{ \App\Support\Money\Money::format($sale->change_given, $currency) }}</td>
            </tr>
        @endif
    </table>

    @if ($sale->status->value === 'voided')
        <p class="muted">{{ $labels['void_reason'] ?? 'Void reason' }}: {{ $sale->void_reason }}</p>
    @endif
</body>
</html>
