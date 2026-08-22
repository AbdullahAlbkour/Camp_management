@extends('layouts.app', ['title' => 'ملف الأسرة'])

@section('content')
<section class="profile-head">
    <div>
        <h2>{{ $household->household_code }}</h2>
        <p>رب الأسرة: {{ $household->head?->full_name ?? '-' }}</p>
    </div>
    <div class="actions">
        @role('admin','registration_officer')
            <a class="secondary" href="{{ route('households.edit', $household) }}"><i data-lucide="pencil"></i><span>تعديل</span></a>
        @endrole
    </div>
</section>

@role('admin','registration_officer')
<section class="panel">
    <h2>إضافة فرد</h2>
    <form method="post" action="{{ route('households.members.store', $household) }}" class="inline-form">
        @csrf
        <label>
            اللاجئ
            <input type="hidden" name="refugee_id" required>
            <div class="async-select" data-async-select="{{ route('lookups.refugees', ['unassigned' => 1]) }}" data-target="refugee_id">
                <input type="search" placeholder="ابحث عن لاجئ بدون أسرة" autocomplete="off">
                <div class="async-options" role="listbox"></div>
            </div>
        </label>
        <input name="relation_to_head" placeholder="صلة القرابة" required>
        <button class="primary" type="submit"><i data-lucide="plus"></i><span>إضافة</span></button>
    </form>
</section>
@endrole

@role('admin','housing_officer')
<section class="panel">
    <h2>نقل الأسرة</h2>
    <form method="post" action="{{ route('housing.household.transfer', $household) }}" class="inline-form">
        @csrf
        <select name="camp_id" required>
            <option value="">المخيم الهدف</option>
            @foreach (\App\Models\Camp::pluck('name', 'id') as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
        <select name="shelter_id">
            <option value="">بدون سكن مباشر</option>
            @foreach (\App\Models\Shelter::with('camp')->where('status', 'active')->get() as $shelter)
                <option value="{{ $shelter->id }}">{{ $shelter->code }} - {{ $shelter->camp?->name }}</option>
            @endforeach
        </select>
        <input name="reason" placeholder="سبب النقل">
        <button class="primary" type="submit"><i data-lucide="move"></i><span>نقل</span></button>
    </form>
</section>
@endrole

<section class="panel">
    <h2>أفراد الأسرة</h2>
    <div class="table-wrap embedded">
        <table>
            <thead><tr><th>الاسم</th><th>الصلة</th><th>المخيم</th><th>السكن</th><th>إجراءات</th></tr></thead>
            <tbody>
                @forelse ($household->members as $member)
                    <tr>
                        <td><a href="{{ route('refugees.show', $member) }}">{{ $member->full_name }}</a></td>
                        <td>{{ $member->relation_to_head }}</td>
                        <td>{{ $member->currentCamp?->name }}</td>
                        <td>{{ $member->currentShelter?->display_name ?? '-' }}</td>
                        <td class="actions">
                            @role('admin','registration_officer')
                                <form method="post" action="{{ route('households.members.destroy', [$household, $member]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="icon-link danger-link" type="submit" title="فصل"><i data-lucide="unlink"></i></button>
                                </form>
                            @endrole
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">لا يوجد أفراد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>مساعدات الأسرة</h2>
    <div class="table-wrap embedded">
        <table>
            <thead><tr><th>النوع</th><th>الكمية</th><th>التاريخ</th></tr></thead>
            <tbody>
                @forelse ($household->aidDistributions as $aid)
                    <tr>
                        <td>{{ $aid->aidType?->name }}</td>
                        <td>{{ $aid->quantity }}</td>
                        <td>{{ $aid->distribution_date }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">لا توجد مساعدات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
