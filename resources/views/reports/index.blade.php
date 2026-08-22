@extends('layouts.app', ['title' => 'التقارير'])

@section('content')
<form method="get" class="filters">
    <select name="report">
        @foreach ([
            'refugees' => 'اللاجئون',
            'households' => 'الأسر',
            'shelters' => 'السكن',
            'transfers' => 'الانتقالات',
            'aid' => 'المساعدات',
            'medical' => 'السجلات الطبية',
            'movement' => 'الحركة',
            'security' => 'التقارير الأمنية',
        ] as $key => $label)
            <option value="{{ $key }}" @selected($report === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="camp_id">
        <option value="">كل المخيمات</option>
        @foreach ($camps as $id => $name)
            <option value="{{ $id }}" @selected(request('camp_id') == $id)>{{ $name }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ request('from') }}">
    <input type="date" name="to" value="{{ request('to') }}">
    <button class="secondary" type="submit"><i data-lucide="filter"></i><span>تصفية</span></button>
    <a class="primary" href="{{ route('reports.export', request()->query()) }}"><i data-lucide="download"></i><span>تصدير Excel/CSV</span></a>
    <a class="secondary" target="_blank" href="{{ route('reports.print', request()->query()) }}"><i data-lucide="printer"></i><span>طباعة PDF</span></a>
</form>

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                @php $first = $rows->first(); @endphp
                @if ($first)
                    @foreach (collect($first->toArray())->keys()->take(8) as $heading)
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
                    @foreach (collect($row->toArray())->take(8) as $value)
                        <td>{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="empty">لا توجد بيانات.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>

{{ $rows->links() }}
@endsection
