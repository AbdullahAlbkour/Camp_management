<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>بطاقة {{ $refugee->full_name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Tajawal', system-ui, sans-serif;
            background: #eef2f1;
            margin: 0;
            padding: 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }
        .card {
            width: 340px;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 12px 30px rgba(15, 47, 40, 0.16);
            border: 1px solid #d8e2df;
        }
        .card-head {
            background: linear-gradient(135deg, #1f6f5c, #12483c);
            color: #fff;
            padding: 14px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-head strong { font-size: 15px; font-weight: 800; }
        .card-head span { font-size: 11px; opacity: 0.85; }
        .card-body { padding: 16px 18px 10px; }
        .card-name { font-size: 19px; font-weight: 800; color: #12483c; margin: 0 0 2px; }
        .card-code { font-size: 12px; color: #6b7d78; margin: 0 0 14px; letter-spacing: 0.5px; }
        dl { display: grid; grid-template-columns: auto 1fr; gap: 6px 12px; margin: 0; font-size: 12.5px; }
        dt { color: #6b7d78; }
        dd { margin: 0; font-weight: 500; color: #16302a; }
        .barcode { padding: 10px 18px 16px; text-align: center; border-top: 1px dashed #d8e2df; margin-top: 12px; }
        .barcode svg { max-width: 100%; height: 52px; }
        .barcode small { display: block; margin-top: 4px; font-size: 11px; letter-spacing: 3px; color: #44544f; }
        .actions { display: flex; gap: 10px; }
        .actions button, .actions a {
            font-family: inherit;
            font-size: 13px;
            padding: 9px 18px;
            border-radius: 9px;
            border: 1px solid #1f6f5c;
            background: #1f6f5c;
            color: #fff;
            cursor: pointer;
            text-decoration: none;
        }
        .actions a { background: #fff; color: #1f6f5c; }
        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none; }
            .card { box-shadow: none; border: 1px solid #999; }
        }
    </style>
</head>
<body>
<div class="actions">
    <button type="button" onclick="window.print()">طباعة البطاقة</button>
    <a href="{{ route('refugees.show', $refugee) }}">رجوع إلى الملف</a>
</div>

<article class="card">
    <header class="card-head">
        <strong>{{ config('app.name') }}</strong>
        <span>بطاقة تعريف</span>
    </header>

    <div class="card-body">
        <h1 class="card-name">{{ $refugee->full_name }}</h1>
        <p class="card-code">{{ $refugee->badge_code }}</p>

        <dl>
            <dt>الجنس</dt><dd>{{ \App\Support\Labels::get('gender', $refugee->gender) }}</dd>
            <dt>العمر</dt><dd>{{ $refugee->age !== null ? $refugee->age.' سنة' : '—' }}</dd>
            <dt>الجنسية</dt><dd>{{ $refugee->nationality ?: '—' }}</dd>
            <dt>رقم الوثيقة</dt><dd>{{ $refugee->document_number ?: '—' }}</dd>
            <dt>المخيم</dt><dd>{{ $refugee->currentCamp?->name ?: '—' }}</dd>
            <dt>الوحدة السكنية</dt><dd>{{ $refugee->currentShelter?->display_name ?: 'بدون سكن' }}</dd>
            <dt>رمز الأسرة</dt><dd>{{ $refugee->household?->household_code ?: '—' }}</dd>
        </dl>
    </div>

    <div class="barcode">
        {!! \App\Support\Code128::svg($refugee->badge_code) !!}
        <small>{{ $refugee->badge_code }}</small>
    </div>
</article>
</body>
</html>
