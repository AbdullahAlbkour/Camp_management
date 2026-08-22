@extends('layouts.app', ['title' => 'التقارير'])

@section('content')
<form method="get" class="filters">
    <select name="report">
        @foreach ($available as $key => $label)
            <option value="{{ $key }}" @selected($report->key === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="camp_id">
        <option value="">كل المخيمات</option>
        @foreach ($camps as $id => $name)
            <option value="{{ $id }}" @selected(request('camp_id') == $id)>{{ $name }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ request('from') }}" title="من تاريخ">
    <input type="date" name="to" value="{{ request('to') }}" title="إلى تاريخ">
    <button class="secondary" type="submit"><i data-lucide="filter"></i><span>تصفية</span></button>
    <a class="primary" href="{{ route('reports.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}">
        <i data-lucide="file-spreadsheet"></i><span>تصدير Excel</span>
    </a>
    <a class="secondary" href="{{ route('reports.export', array_merge(request()->query(), ['format' => 'csv'])) }}">
        <i data-lucide="download"></i><span>تصدير CSV</span>
    </a>
    <a class="secondary" target="_blank" href="{{ route('reports.print', request()->query()) }}">
        <i data-lucide="printer"></i><span>طباعة</span>
    </a>
</form>

<section class="toolbar">
    <div>
        <h2>{{ $report->label }}</h2>
        <p>عدد النتائج: {{ number_format($rows->total()) }}</p>
    </div>
</section>

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
                <tr><td class="empty" colspan="{{ max(1, count($report->headings())) }}">لا توجد بيانات مطابقة للفلاتر.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>

{{ $rows->links() }}
@endsection
