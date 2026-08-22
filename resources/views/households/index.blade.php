@extends('layouts.app', ['title' => 'الأسر'])

@section('content')
<section class="toolbar">
    <div>
        <h2>الأسر</h2>
        <p>{{ number_format($rows->total()) }} سجل</p>
    </div>
    @role('admin','registration_officer')
        <a class="primary" href="{{ route('households.create') }}"><i data-lucide="plus"></i><span>إنشاء أسرة</span></a>
    @endrole
</section>

<form method="get" class="filters">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="رمز الأسرة، رب الأسرة، رقم الوثيقة">
    <select name="status">
        <option value="">كل الحالات</option>
        <option value="active" @selected(request('status') === 'active')>فعالة</option>
        <option value="archived" @selected(request('status') === 'archived')>مؤرشفة</option>
    </select>
    <button class="secondary" type="submit"><i data-lucide="search"></i><span>بحث</span></button>
    @if (request()->hasAny(['q', 'status']))
        <a class="secondary" href="{{ route('households.index') }}"><i data-lucide="x"></i><span>مسح</span></a>
    @endif
</form>

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>رمز الأسرة</th>
                <th>رب الأسرة</th>
                <th>عدد الأفراد</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $household)
                <tr>
                    <td><strong>{{ $household->household_code }}</strong></td>
                    <td>{{ $household->head?->full_name ?? '-' }}</td>
                    <td>{{ $household->members_count }}</td>
                    <td><span class="badge {{ $household->status }}">{{ $household->status === 'active' ? 'فعالة' : 'مؤرشفة' }}</span></td>
                    <td class="actions">
                        <a class="icon-link" href="{{ route('households.show', $household) }}" title="ملف الأسرة"><i data-lucide="eye"></i></a>
                        @role('admin','registration_officer')
                            <a class="icon-link" href="{{ route('households.edit', $household) }}" title="تعديل"><i data-lucide="pencil"></i></a>
                        @endrole
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">لا توجد أسر مطابقة.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>

{{ $rows->links() }}
@endsection
