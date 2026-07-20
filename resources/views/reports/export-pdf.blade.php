<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $report->label() }} — {{ $business->name }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111;
            margin: 24px;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 4px;
        }

        .muted { color: #555; }

        .meta {
            margin-bottom: 18px;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .summary td {
            padding: 6px 8px;
            border: 1px solid #ddd;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
        }

        table.items th,
        table.items td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }

        table.items th {
            background: #f3f3f3;
        }
    </style>
</head>
<body>
    <h1>{{ $report->label() }}</h1>
    <p class="muted">{{ $business->name }}</p>

    <div class="meta muted">
        <div>Period: {{ $filter->dateFrom->toDateString() }} to {{ $filter->dateTo->toDateString() }}</div>
        <div>Generated: {{ $generatedAt->format('Y-m-d H:i') }}</div>
    </div>

    @if (! empty($summary))
        <table class="summary">
            @foreach ($summary as $key => $value)
                <tr>
                    <td><strong>{{ ucwords(str_replace('_', ' ', $key)) }}</strong></td>
                    <td>{{ is_scalar($value) ? $value : json_encode($value) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (! empty($headers))
        <table class="items">
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">No rows for the selected filters.</p>
    @endif
</body>
</html>
