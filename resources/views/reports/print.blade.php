<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report->label }}</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <style>
        body { background: #fff; }
        .print-page { padding: 28px; }
        .print-actions { margin-bottom: 16px; }
        .print-meta { display: flex; flex-wrap: wrap; gap: 8px 24px; font-size: 13px; color: #555; margin-bottom: 18px; }
        .print-meta strong { color: #222; }
        @media print {
            .print-actions { display: none; }
            .table-wrap { box-shadow: none; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>
<main class="print-page">
    <div class="print-actions">
        <button class="primary" onclick="window.print()">طباعة</button>
    </div>

    <h1>تقرير {{ $report->label }}</h1>

    <div class="print-meta">
        <span><strong>أصدره:</strong> {{ auth()->user()?->name }}</span>
        <span><strong>تاريخ الإصدار:</strong> {{ now()->format('Y-m-d H:i') }}</span>
        <span><strong>المخيم:</strong> {{ $filters['camp_id'] ? ($camps[$filters['camp_id']] ?? '—') : 'كل المخيمات' }}</span>
        <span><strong>من:</strong> {{ $filters['from'] ?: 'البداية' }}</span>
        <span><strong>إلى:</strong> {{ $filters['to'] ?: 'اليوم' }}</span>
        <span><strong>عدد السجلات:</strong> {{ number_format($rows->count()) }}</span>
    </div>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    @foreach ($report->headings() as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($report->rowText($row) as $value)
                            <td>{{ $value }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td class="empty" colspan="{{ max(1, count($report->headings())) }}">لا توجد بيانات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
