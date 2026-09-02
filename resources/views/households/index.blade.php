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

@include('partials.filter-bar', ['filter' => $filter, 'route' => 'households.index', 'total' => $rows->total()])

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                @include('partials.sortable-header', ['key' => 'code', 'label' => 'رمز الأسرة', 'route' => 'households.index'])
                <th>رب الأسرة</th>
                <th>المخيم</th>
                @include('partials.sortable-header', ['key' => 'members', 'label' => 'عدد الأفراد', 'route' => 'households.index'])
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $household)
                <tr>
                    <td><a class="row-link" href="{{ route('households.show', $household) }}">{{ $household->household_code }}</a></td>
                    <td>{{ $household->head?->full_name ?? '—' }}</td>
                    <td>{{ $household->head?->currentCamp?->name ?? '—' }}</td>
                    <td>{{ $household->members_count }}</td>
                    <td>
                        <span class="badge {{ $household->status }}">
                            {{ $household->status === 'active' ? 'فعالة' : 'مؤرشفة' }}
                        </span>
                    </td>
                    <td class="actions">
                        <a class="icon-link" href="{{ route('households.show', $household) }}" title="ملف الأسرة"><i data-lucide="eye"></i></a>
                        @role('admin','registration_officer')
                            <a class="icon-link" href="{{ route('households.edit', $household) }}" title="تعديل"><i data-lucide="pencil"></i></a>
                            @include('partials.archive-button', [
                                'resource' => 'households',
                                'id' => $household->id,
                                'label' => $household->household_code,
                            ])
                        @endrole
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">لا توجد أسر مطابقة للبحث أو الفلاتر.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>

{{ $rows->links() }}
@endsection
