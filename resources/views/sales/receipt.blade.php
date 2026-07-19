<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sale->sale_number }} — Receipt</title>
    <style>
        :root {
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Courier New", Courier, monospace;
            color: #111;
            background: #fff;
        }

        .toolbar {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            padding: 1rem;
            border-bottom: 1px solid #ddd;
            background: #f7f7f7;
        }

        .toolbar button,
        .toolbar a {
            font: inherit;
            padding: 0.5rem 0.9rem;
            border: 1px solid #222;
            background: #fff;
            color: #111;
            text-decoration: none;
            cursor: pointer;
        }

        .sheet {
            width: 80mm;
            max-width: 100%;
            margin: 1rem auto;
            padding: 0.5rem 0.75rem 1.25rem;
        }

        .a4 {
            width: 210mm;
            max-width: 100%;
            padding: 18mm 16mm;
        }

        .center { text-align: center; }
        .right { text-align: right; }
        .muted { color: #444; font-size: 0.85rem; }

        h1 {
            font-size: 1.1rem;
            margin: 0 0 0.25rem;
        }

        .meta, .totals, .items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.75rem;
        }

        .items th,
        .items td,
        .totals td {
            padding: 0.2rem 0;
            vertical-align: top;
        }

        .items th {
            border-bottom: 1px dashed #333;
            text-align: left;
            font-weight: 700;
        }

        .items .qty { width: 12%; }
        .items .price, .items .total { width: 22%; text-align: right; }

        .totals td:last-child { text-align: right; font-weight: 700; }
        .rule { border-top: 1px dashed #333; margin: 0.75rem 0; }

        .void-banner {
            border: 2px solid #111;
            text-align: center;
            font-weight: 700;
            padding: 0.35rem;
            margin: 0.5rem 0;
            text-transform: uppercase;
        }

        @media print {
            .toolbar { display: none !important; }
            .sheet { margin: 0; width: 80mm; }
            .sheet.a4 { width: auto; padding: 12mm; }
            @page { margin: 4mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print</button>
        <button type="button" onclick="document.getElementById('receipt').classList.toggle('a4')">Thermal / A4</button>
        <a href="{{ route('sales.show', $sale) }}">Back to sale</a>
    </div>

    <main id="receipt" class="sheet">
        <div class="center">
            <h1>{{ $business->name }}</h1>
            <div class="muted">{{ $sale->branch?->name }}</div>
        </div>

        @if ($sale->status->value === 'voided')
            <div class="void-banner">VOIDED</div>
        @endif

        <div class="rule"></div>

        <table class="meta">
            <tr>
                <td>Receipt</td>
                <td class="right">{{ $sale->sale_number }}</td>
            </tr>
            <tr>
                <td>Date</td>
                <td class="right">{{ $sale->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
            </tr>
            <tr>
                <td>Cashier</td>
                <td class="right">{{ $sale->cashier?->name }}</td>
            </tr>
            <tr>
                <td>Customer</td>
                <td class="right">{{ $sale->customer?->name ?? $sale->customer_name ?? 'Walk-in' }}</td>
            </tr>
            <tr>
                <td>Payment</td>
                <td class="right">{{ $sale->payment_method->label() }}</td>
            </tr>
        </table>

        <div class="rule"></div>

        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="qty">Qty</th>
                    <th class="price">Price</th>
                    <th class="total">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td>
                            {{ $item->product_name }}
                            <div class="muted">{{ $item->sku }}</div>
                        </td>
                        <td class="qty">{{ $item->quantity }}</td>
                        <td class="price">{{ \App\Support\Money\Money::format($item->unit_price, $currency) }}</td>
                        <td class="total">{{ \App\Support\Money\Money::format($item->line_total, $currency) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="rule"></div>

        <table class="totals">
            <tr>
                <td>Subtotal</td>
                <td>{{ \App\Support\Money\Money::format($sale->subtotal, $currency) }}</td>
            </tr>
            @if ($sale->discount_amount > 0)
                <tr>
                    <td>Discount</td>
                    <td>-{{ \App\Support\Money\Money::format($sale->discount_amount, $currency) }}</td>
                </tr>
            @endif
            <tr>
                <td>Total</td>
                <td>{{ \App\Support\Money\Money::format($sale->total, $currency) }}</td>
            </tr>
        </table>

        <div class="rule"></div>
        <div class="center muted">Thank you for shopping with us.</div>
        @if ($sale->status->value === 'voided')
            <div class="center muted">Void reason: {{ $sale->void_reason }}</div>
        @endif
    </main>
</body>
</html>
