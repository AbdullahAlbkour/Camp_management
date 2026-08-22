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
            <dt>الجنس</dt><dd>{{ $refugee->gender }}</dd>
            <dt>تاريخ الميلاد</dt><dd>{{ optional($refugee->date_of_birth)->format('Y-m-d') ?? '-' }}</dd>
            <dt>الجنسية</dt><dd>{{ $refugee->nationality ?? '-' }}</dd>
            <dt>الهاتف</dt><dd>{{ $refugee->phone ?? '-' }}</dd>
            <dt>الحالة</dt><dd>{{ $refugee->status }}</dd>
            <dt>الوجود</dt><dd>{{ $refugee->presence_status }}</dd>
        </dl>
    </article>

    <article class="panel">
        <h2>السكن الحالي</h2>
        <dl>
            <dt>المخيم</dt><dd>{{ $refugee->currentCamp?->name }}</dd>
            <dt>الوحدة</dt><dd>{{ $refugee->currentShelter?->display_name ?? 'بدون سكن' }}</dd>
            <dt>نوع السكن</dt><dd>{{ $refugee->currentShelter?->type_label ?? '-' }}</dd>
            <dt>حالة السكن</dt><dd>{{ $refugee->housing_status }}</dd>
            <dt>الأسرة</dt><dd>{{ $refugee->household?->household_code ?? '-' }}</dd>
            <dt>صلة القرابة</dt><dd>{{ $refugee->relation_to_head ?? '-' }}</dd>
        </dl>
    </article>
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
