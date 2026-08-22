@extends('layouts.app', ['title' => 'الأرشيف'])

@section('content')
<section class="toolbar">
    <div>
        <h2>الأرشيف</h2>
        <p>السجلات المؤرشفة تبقى محفوظة حتى لا تفقد التقارير التاريخية مرجعها، ويمكن استرجاعها في أي وقت.</p>
    </div>
</section>

<form method="get" class="filters">
    <select name="resource" onchange="this.form.submit()">
        @foreach ($available as $key => $label)
            <option value="{{ $key }}" @selected($resource === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <noscript><button class="secondary" type="submit">عرض</button></noscript>
</form>

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>الرقم</th>
                <th>السجل</th>
                <th>تاريخ الأرشفة</th>
                <th>إجراء</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->full_name ?? $row->name ?? $row->household_code ?? $row->code ?? '—' }}</td>
                    <td>{{ $row->deleted_at?->format('Y-m-d H:i') }}</td>
                    <td>
                        <form method="post" action="{{ route('archive.restore', [$resource, $row->id]) }}">
                            @csrf
                            <button class="secondary" type="submit"><i data-lucide="rotate-ccw"></i><span>استرجاع</span></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td class="empty" colspan="4">لا توجد سجلات مؤرشفة.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>

{{ $rows->links() }}
@endsection
