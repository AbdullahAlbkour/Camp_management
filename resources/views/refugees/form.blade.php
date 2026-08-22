@extends('layouts.app', ['title' => $title])

@section('content')
<section class="toolbar">
    <div>
        <h2>{{ $title }}</h2>
        <p>المخيم مطلوب، والسكن اختياري.</p>
    </div>
    <a class="secondary" href="{{ route('refugees.index') }}"><i data-lucide="arrow-right"></i><span>رجوع</span></a>
</section>

@if (session('duplicates'))
    <section class="panel warning-panel">
        <h2>سجلات مشابهة</h2>
        <div class="table-wrap embedded">
            <table>
                <thead><tr><th>الاسم</th><th>الوثيقة</th><th>المخيم</th><th>سبب التشابه</th><th>فتح الملف</th></tr></thead>
                <tbody>
                    @foreach (session('duplicates') as $duplicate)
                        <tr>
                            <td>{{ $duplicate->full_name }}</td>
                            <td>{{ $duplicate->document_number ?? '-' }}</td>
                            <td>{{ $duplicate->currentCamp?->name }}</td>
                            <td>{{ implode('، ', $duplicate->match_reasons ?? []) }}</td>
                            <td><a href="{{ route('refugees.show', $duplicate) }}" target="_blank">عرض</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

<form method="post" action="{{ $action }}" class="form-grid refugee-form">
    @csrf
    @if ($method === 'PUT') @method('PUT') @endif

    <div class="form-section-title full">
        <i data-lucide="user-round"></i>
        <div>
            <strong>البيانات الشخصية</strong>
            <span>المعلومات الأساسية المطلوبة للتعريف باللاجئ.</span>
        </div>
    </div>
    <label>الاسم الأول <input name="first_name" value="{{ old('first_name', $refugee->first_name) }}" required></label>
    <label>اسم الأب <input name="father_name" value="{{ old('father_name', $refugee->father_name) }}"></label>
    <label>الكنية <input name="last_name" value="{{ old('last_name', $refugee->last_name) }}" required></label>
    <label>الجنس
        <select name="gender" required>
            <option value="">-- اختر --</option>
            <option value="male" @selected(old('gender', $refugee->gender) === 'male')>ذكر</option>
            <option value="female" @selected(old('gender', $refugee->gender) === 'female')>أنثى</option>
            <option value="other" @selected(old('gender', $refugee->gender) === 'other')>آخر</option>
        </select>
    </label>
    <label>تاريخ الميلاد <input type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($refugee->date_of_birth)->format('Y-m-d')) }}"></label>
    <label>الجنسية <input name="nationality" value="{{ old('nationality', $refugee->nationality) }}"></label>
    <label>رقم الوثيقة <input name="document_number" value="{{ old('document_number', $refugee->document_number) }}"></label>
    <label>الهاتف <input name="phone" value="{{ old('phone', $refugee->phone) }}"></label>
    <label>الحالة الاجتماعية <input name="marital_status" value="{{ old('marital_status', $refugee->marital_status) }}"></label>

    <div class="form-section-title full">
        <i data-lucide="tent"></i>
        <div>
            <strong>السكن والإقامة</strong>
            <span>حدد المخيم والسكن والأسرة المرتبطة إن وجدت.</span>
        </div>
    </div>
    <label>المخيم الحالي
        <select name="current_camp_id" data-camp-select required>
            <option value="">-- اختر --</option>
            @foreach ($camps as $id => $name)
                <option value="{{ $id }}" @selected(old('current_camp_id', $refugee->current_camp_id) == $id)>{{ $name }}</option>
            @endforeach
        </select>
    </label>
    <label>الوحدة السكنية
        <select name="current_shelter_id" data-shelter-select>
            <option value="">بدون سكن</option>
            @foreach ($shelters as $shelter)
                <option value="{{ $shelter->id }}" data-camp="{{ $shelter->camp_id }}" @selected(old('current_shelter_id', $refugee->current_shelter_id) == $shelter->id)>
                    {{ $shelter->display_name }} - {{ $shelter->camp?->name }}
                </option>
            @endforeach
        </select>
    </label>
    <label>الأسرة
        <select name="household_id">
            <option value="">بدون أسرة</option>
            @foreach ($households as $id => $code)
                <option value="{{ $id }}" @selected(old('household_id', $refugee->household_id) == $id)>{{ $code }}</option>
            @endforeach
        </select>
    </label>
    <label>صلة القرابة <input name="relation_to_head" value="{{ old('relation_to_head', $refugee->relation_to_head) }}"></label>
    <label>الحالة
        <select name="status">
            <option value="active" @selected(old('status', $refugee->status ?: 'active') === 'active')>فعال</option>
            <option value="inactive" @selected(old('status', $refugee->status) === 'inactive')>غير فعال</option>
            <option value="archived" @selected(old('status', $refugee->status) === 'archived')>مؤرشف</option>
        </select>
    </label>
    <label>حالة الوجود
        <select name="presence_status">
            <option value="inside" @selected(old('presence_status', $refugee->presence_status ?: 'inside') === 'inside')>داخل المخيم</option>
            <option value="outside" @selected(old('presence_status', $refugee->presence_status) === 'outside')>خارج المخيم</option>
        </select>
    </label>

    <div class="form-section-title full">
        <i data-lucide="clipboard-list"></i>
        <div>
            <strong>ملاحظات ومراجعة</strong>
            <span>أي تفاصيل إضافية أو تأكيد للسجلات المشابهة.</span>
        </div>
    </div>
    <label class="full">ملاحظات <textarea name="notes" rows="4">{{ old('notes', $refugee->notes) }}</textarea></label>

    @if (session('duplicates'))
        <label class="checkbox-row full">
            <input type="checkbox" name="confirmed_duplicate_check" value="1" required>
            <span>راجعت السجلات المشابهة وأؤكد المتابعة كتسجيل جديد.</span>
        </label>
    @endif

    <div class="form-actions">
        <button class="primary" type="submit"><i data-lucide="save"></i><span>حفظ</span></button>
    </div>
</form>
@endsection
