@extends('layouts.app', ['title' => 'اللاجئون'])

@section('content')
<section class="toolbar">
    <div>
        <h2>بحث اللاجئين</h2>
        <p>{{ number_format($rows->total()) }} سجل</p>
    </div>
    @role('admin','registration_officer')
        <a class="primary" href="{{ route('refugees.create') }}"><i data-lucide="user-plus"></i><span>تسجيل لاجئ</span></a>
    @endrole
</section>

@include('partials.filter-bar', ['filter' => $filter, 'route' => 'refugees.index', 'total' => $rows->total()])

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                @include('partials.sortable-header', ['key' => 'name', 'label' => 'الاسم', 'route' => 'refugees.index'])
                @include('partials.sortable-header', ['key' => 'document', 'label' => 'رقم الوثيقة', 'route' => 'refugees.index'])
                <th>المخيم</th>
                <th>السكن</th>
                <th>حالة السكن</th>
                <th>الوجود</th>
                @include('partials.sortable-header', ['key' => 'birth', 'label' => 'العمر', 'route' => 'refugees.index'])
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $refugee)
                <tr>
                    <td>
                        <a class="row-link" href="{{ route('refugees.show', $refugee) }}">{{ $refugee->full_name }}</a>
                        <small class="row-sub">{{ $refugee->badge_code }}</small>
                    </td>
                    <td>{{ $refugee->document_number ?? '—' }}</td>
                    <td>{{ $refugee->currentCamp?->name ?? '—' }}</td>
                    <td>{{ $refugee->currentShelter?->display_name ?? '—' }}</td>
                    <td>
                        <span class="badge {{ $refugee->housing_status }}">
                            {{ \App\Support\Labels::get('housing_status', $refugee->housing_status) }}
                        </span>
                    </td>
                    <td>{{ \App\Support\Labels::get('presence_status', $refugee->presence_status) }}</td>
                    <td>{{ $refugee->age !== null ? $refugee->age : '—' }}</td>
                    <td class="actions">
                        <a class="icon-link" href="{{ route('refugees.show', $refugee) }}" title="ملف اللاجئ"><i data-lucide="eye"></i></a>
                        @role('admin','registration_officer')
                            <a class="icon-link" href="{{ route('refugees.edit', $refugee) }}" title="تعديل"><i data-lucide="pencil"></i></a>
                        @endrole
                        @role('admin','housing_officer')
                            <a class="icon-link" href="{{ route('housing.transfer.form', $refugee) }}" title="السكن"><i data-lucide="bed"></i></a>
                        @endrole
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">لا توجد نتائج مطابقة للبحث أو الفلاتر.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>

{{ $rows->links() }}
@endsection
