<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $customer->name }} — Account Statement</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111;
            margin: 24px;
        }

        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 24px 0 8px; }
        .muted { color: #555; }

        .header {
            width: 100%;
            margin-bottom: 24px;
        }

        .header td { vertical-align: top; }
        .right { text-align: right; }

        table.ledger {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        table.ledger th,
        table.ledger td {
            border-bottom: 1px solid #ddd;
            padding: 8px 6px;
            text-align: left;
        }

        table.ledger th {
            background: #f3f3f3;
            font-weight: 700;
        }

        .money { text-align: right; white-space: nowrap; }

        .summary {
            width: 45%;
            margin-left: auto;
            margin-top: 16px;
            border-collapse: collapse;
        }

        .summary td {
            padding: 6px 0;
        }

        .summary td:last-child {
            text-align: right;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>{{ $business->name }}</h1>
                <div class="muted">Customer account statement</div>
            </td>
            <td class="right">
                <div><strong>Period:</strong> {{ $from }} — {{ $to }}</div>
                <div class="muted">Generated {{ now()->format('Y-m-d H:i') }}</div>
            </td>
        </tr>
    </table>

    <h2>Customer</h2>
    <div><strong>{{ $customer->name }}</strong></div>
    @if ($customer->phone)
        <div class="muted">{{ $customer->phone }}</div>
    @endif
    @if ($customer->email)
        <div class="muted">{{ $customer->email }}</div>
    @endif
    @if ($customer->address)
        <div class="muted">{{ $customer->address }}</div>
    @endif

    <table class="summary">
        <tr>
            <td>Outstanding balance</td>
            <td>{{ $outstanding_balance_formatted }}</td>
        </tr>
        @if ($customer->credit_limit !== null)
            <tr>
                <td>Credit limit</td>
                <td>{{ \App\Support\Money\Money::format($customer->credit_limit, $currency) }}</td>
            </tr>
        @endif
    </table>

    <h2>Ledger</h2>
    @if ($entries->isEmpty())
        <p class="muted">No ledger activity in this period.</p>
    @else
        <table class="ledger">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th class="money">Debit</th>
                    <th class="money">Credit</th>
                    <th class="money">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td>{{ $entry['entry_date'] }}</td>
                        <td>{{ $entry['description'] }}</td>
                        <td>{{ $entry['type_label'] }}</td>
                        <td class="money">
                            @if ($entry['direction'] === 'debit')
                                {{ $entry['amount_formatted'] }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="money">
                            @if ($entry['direction'] === 'credit')
                                {{ $entry['amount_formatted'] }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="money">{{ $entry['balance_after_formatted'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
