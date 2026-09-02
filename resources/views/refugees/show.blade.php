@extends('layouts.app', ['title' => 'ملف اللاجئ'])

@section('content')
<section class="profile-head">
    <div>
        <h2>{{ $refugee->full_name }}</h2>
        <p>{{ $refugee->badge_code }} • {{ $refugee->document_number ?? 'بدون رقم وثيقة' }}</p>
    </div>
    <div class="actions">
        @role('admin','registration_officer')
            <a class="secondary" href="{{ route('refugees.edit', $refugee) }}"><i data-lucide="pencil"></i><span>تعديل</span></a>
        @endrole
        @role('admin','housing_officer')
            <a class="primary" href="{{ route('housing.transfer.form', $refugee) }}"><i data-lucide="bed"></i><span>السكن</span></a>
        @endrole
        <a class="secondary" target="_blank" href="{{ route('refugees.card', $refugee) }}"><i data-lucide="id-card"></i><span>بطاقة التعريف</span></a>
        @role('admin','registration_officer')
            {{-- The confirm says where the record goes, because it is archived
                 rather than erased and the wording is what the user acts on. --}}
            @php
                $confirmMessage = 'سيُنقل سجل '.$refugee->full_name
                    .' إلى الأرشيف ويمكن استرجاعه لاحقًا.'."\n\n".'هل تريد المتابعة؟';
            @endphp
            {{-- Js::from and not string interpolation: {{ }} escapes for HTML,
                 and the attribute is decoded again before the JS runs, so an
                 apostrophe in a name would close the string literal and leave
                 the button dead. UNESCAPED_UNICODE keeps the Arabic readable in
                 the source; the quote characters are still escaped. --}}
            <form method="post" action="{{ route('refugees.destroy', $refugee) }}"
                  onsubmit="return confirm({{ Js::from($confirmMessage, JSON_UNESCAPED_UNICODE) }});">
                @csrf
                @method('DELETE')
                <button class="secondary danger-link" type="submit"><i data-lucide="trash-2"></i><span>حذف</span></button>
            </form>
        @endrole
    </div>
</section>

<section class="refugee-summary">
    <article class="summary-hero">
        <span class="summary-avatar">{{ mb_substr($refugee->first_name, 0, 1) }}</span>
        <div>
            <h2>{{ $refugee->full_name }}</h2>
            <p>{{ $refugee->document_number ?? 'بدون رقم وثيقة' }}</p>
        </div>
        <div class="summary-badges">
            <span class="badge {{ $refugee->status }}">{{ \App\Support\Labels::get('refugee_status', $refugee->status) }}</span>
            <span class="badge {{ $refugee->housing_status }}">{{ $refugee->currentShelter?->display_name ?? 'بدون سكن' }}</span>
            <span class="badge">{{ \App\Support\Labels::get('presence_status', $refugee->presence_status) }}</span>
        </div>
    </article>

    <article class="summary-card">
        <i data-lucide="map-pin"></i>
        <span>المخيم</span>
        <strong>{{ $refugee->currentCamp?->name ?? '-' }}</strong>
    </article>
    <article class="summary-card">
        <i data-lucide="home"></i>
        <span>نوع السكن</span>
        <strong>{{ $refugee->currentShelter?->type_label ?? '-' }}</strong>
    </article>
    <article class="summary-card">
        <i data-lucide="house"></i>
        <span>الأسرة</span>
        <strong>{{ $refugee->household?->household_code ?? 'بدون أسرة' }}</strong>
    </article>
</section>

<section class="detail-grid">
    <article class="panel">
        <h2>البيانات الأساسية</h2>
        <dl>
            <dt>الجنس</dt><dd>{{ \App\Support\Labels::get('gender', $refugee->gender) }}</dd>
            <dt>تاريخ الميلاد</dt><dd>{{ optional($refugee->date_of_birth)->format('Y-m-d') ?? '-' }}</dd>
            <dt>الجنسية</dt><dd>{{ $refugee->nationality ?? '-' }}</dd>
            <dt>الهاتف</dt><dd>{{ $refugee->phone ?? '-' }}</dd>
            <dt>الحالة</dt><dd>{{ \App\Support\Labels::get('refugee_status', $refugee->status) }}</dd>
            <dt>الوجود</dt><dd>{{ \App\Support\Labels::get('presence_status', $refugee->presence_status) }}</dd>
        </dl>
    </article>

    <article class="panel">
        <h2>السكن الحالي</h2>
        <dl>
            <dt>المخيم</dt><dd>{{ $refugee->currentCamp?->name }}</dd>
            <dt>الوحدة</dt><dd>{{ $refugee->currentShelter?->display_name ?? 'بدون سكن' }}</dd>
            <dt>نوع السكن</dt><dd>{{ $refugee->currentShelter?->type_label ?? '-' }}</dd>
            <dt>حالة السكن</dt><dd>{{ \App\Support\Labels::get('housing_status', $refugee->housing_status) }}</dd>
            <dt>الأسرة</dt><dd>{{ $refugee->household?->household_code ?? '-' }}</dd>
            <dt>صلة القرابة</dt><dd>{{ $refugee->relation_to_head ?? '-' }}</dd>
        </dl>
    </article>
</section>

<section class="panel attachments-panel">
    <h2>المرفقات</h2>

    @php
        $visibleAttachments = $refugee->attachments->filter(
            fn ($attachment) => $attachment->category !== 'medical'
                || auth()->user()?->hasAnyRole(['admin', 'medical_officer'])
        );
    @endphp

    @if ($visibleAttachments->isEmpty())
        <p class="muted">لا توجد ملفات مرفقة بهذا السجل.</p>
    @else
        <ul class="attachment-list">
            @foreach ($visibleAttachments as $attachment)
                <li>
                    <i data-lucide="{{ $attachment->isImage() ? 'image' : 'file-text' }}"></i>
                    <div>
                        <a href="{{ route('attachments.download', $attachment) }}">{{ $attachment->original_name }}</a>
                        <small>{{ $attachment->category_label }} • {{ $attachment->human_size }} • {{ $attachment->created_at?->format('Y-m-d') }}</small>
                        @if ($attachment->description)<small>{{ $attachment->description }}</small>@endif
                    </div>
                    @role('admin','registration_officer','medical_officer')
                        <form method="post" action="{{ route('attachments.destroy', $attachment) }}"
                              onsubmit="return confirm('حذف المرفق {{ $attachment->original_name }}؟');">
                            @csrf
                            @method('DELETE')
                            <button class="icon-button" type="submit" title="حذف"><i data-lucide="trash-2"></i></button>
                        </form>
                    @endrole
                </li>
            @endforeach
        </ul>
    @endif

    @role('admin','registration_officer','medical_officer')
        <form method="post" action="{{ route('refugees.attachments.store', $refugee) }}"
              enctype="multipart/form-data" class="attachment-form">
            @csrf
            <label>الملف
                <input type="file" name="file" required
                       accept=".pdf,.jpg,.jpeg,.png,.webp">
            </label>
            <label>التصنيف
                <select name="category" required>
                    @foreach (\App\Services\AttachmentService::CATEGORIES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>وصف مختصر
                <input type="text" name="description" maxlength="255" placeholder="اختياري">
            </label>
            <button class="primary" type="submit"><i data-lucide="upload"></i><span>إرفاق</span></button>
        </form>
        <p class="muted small">الصيغ المقبولة: PDF، JPG، PNG، WEBP — بحد أقصى 8 ميجابايت.</p>
    @endrole
</section>

@include('refugees.timeline', ['title' => 'تاريخ الانتقالات', 'rows' => $refugee->residencyTransfers, 'fields' => ['transfer_type', 'from_camp_id', 'to_camp_id', 'from_shelter_id', 'to_shelter_id', 'transferred_at']])
@include('refugees.timeline', ['title' => 'المساعدات الفردية', 'rows' => $refugee->aidDistributions, 'fields' => ['aidType.name', 'quantity', 'distribution_date']])
@include('refugees.timeline', ['title' => 'مساعدات الأسرة', 'rows' => $householdAid, 'fields' => ['aidType.name', 'quantity', 'distribution_date']])

@role('admin','medical_officer')
    @include('refugees.timeline', ['title' => 'السجلات الطبية', 'rows' => $refugee->medicalRecords, 'fields' => ['medicalService.name', 'record_date', 'diagnosis', 'needs_follow_up', 'follow_up_date']])
@endrole
@if (auth()->user()->role?->name === 'manager')
    <section class="panel"><h2>السجلات الطبية</h2><p>{{ $refugee->medicalRecords->count() }} سجل، منها {{ $refugee->medicalRecords->where('needs_follow_up', true)->count() }} تحتاج متابعة.</p></section>
@endif

@include('refugees.timeline', ['title' => 'حركة الدخول والخروج', 'rows' => $refugee->entryExitLogs, 'fields' => ['movement_type', 'checkpoint.name', 'movement_datetime', 'reason']])

@role('admin','security_officer','manager')
    @include('refugees.timeline', ['title' => 'التقارير الأمنية', 'rows' => $refugee->securityReports, 'fields' => ['incident_type', 'severity', 'report_date', 'description', 'action_taken']])
@endrole
@endsection
