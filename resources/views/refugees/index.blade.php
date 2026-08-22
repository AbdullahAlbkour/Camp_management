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

<form method="get" class="filters">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="الاسم، الوثيقة، الهاتف">
    <select name="camp_id">
        <option value="">كل المخيمات</option>
        @foreach ($camps as $id => $name)
            <option value="{{ $id }}" @selected(request('camp_id') == $id)>{{ $name }}</option>
        @endforeach
    </select>
    <select name="housing_status">
        <option value="">كل حالات السكن</option>
        <option value="assigned" @selected(request('housing_status') === 'assigned')>مخصص</option>
        <option value="unassigned" @selected(request('housing_status') === 'unassigned')>بدون سكن</option>
    </select>
    <button class="secondary" type="submit"><i data-lucide="search"></i><span>بحث</span></button>
</form>

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>الاسم</th>
                <th>رقم الوثيقة</th>
                <th>المخيم</th>
                <th>السكن</th>
                <th>حالة السكن</th>
                <th>الوجود</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $refugee)
                <tr>
                    <td>{{ $refugee->full_name }}</td>
                    <td>{{ $refugee->document_number ?? '-' }}</td>
                    <td>{{ $refugee->currentCamp?->name }}</td>
                    <td>{{ $refugee->currentShelter?->display_name ?? '-' }}</td>
                    <td><span class="badge {{ $refugee->housing_status }}">{{ $refugee->housing_status }}</span></td>
                    <td>{{ $refugee->presence_status }}</td>
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
                <tr><td colspan="7" class="empty">لا توجد نتائج.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>

{{ $rows->links() }}
@endsection
