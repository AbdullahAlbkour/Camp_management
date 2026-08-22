<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تقرير {{ $report }}</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <style>
        body { background: #fff; }
        .print-page { padding: 28px; }
        .print-actions { margin-bottom: 16px; }
        @media print { .print-actions { display: none; } .table-wrap { box-shadow: none; } }
    </style>
</head>
<body>
<main class="print-page">
    <div class="print-actions">
        <button class="primary" onclick="window.print()">طباعة</button>
    </div>
    <h1>تقرير {{ $report }}</h1>
    <p>المستخدم: {{ auth()->user()?->name }} | التاريخ: {{ now()->format('Y-m-d H:i') }}</p>
    <p>الفلاتر: {{ json_encode($filters, JSON_UNESCAPED_UNICODE) }}</p>
    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    @php $first = $rows->first(); @endphp
                    @if ($first)
                        @foreach (collect($first->toArray())->keys()->take(10) as $heading)
                            <th>{{ $heading }}</th>
                        @endforeach
                    @else
                        <th>النتيجة</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach (collect($row->toArray())->take(10) as $value)
                            <td>{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td class="empty">لا توجد بيانات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
