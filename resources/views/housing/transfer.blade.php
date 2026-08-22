@extends('layouts.app', ['title' => 'السكن والانتقال'])

@section('content')
<section class="profile-head">
    <div>
        <h2>{{ $refugee->full_name }}</h2>
        <p>{{ $refugee->currentCamp?->name }} / {{ $refugee->currentShelter?->code ?? 'بدون سكن' }}</p>
    </div>
    <a class="secondary" href="{{ route('refugees.show', $refugee) }}"><i data-lucide="arrow-right"></i><span>ملف اللاجئ</span></a>
</section>

<form method="post" action="{{ $action }}" class="form-grid">
    @csrf
    <label>المخيم الهدف
        <select name="camp_id" data-camp-select required>
            <option value="">-- اختر --</option>
            @foreach ($camps as $id => $name)
                <option value="{{ $id }}" @selected($refugee->current_camp_id == $id)>{{ $name }}</option>
            @endforeach
        </select>
    </label>
    <label>الوحدة السكنية
        <select name="shelter_id" data-shelter-select>
            <option value="">بدون سكن</option>
            @foreach ($shelters as $shelter)
                <option value="{{ $shelter->id }}" data-camp="{{ $shelter->camp_id }}" @selected($refugee->current_shelter_id == $shelter->id)>
                    {{ $shelter->display_name }} - {{ $shelter->camp?->name }}
                </option>
            @endforeach
        </select>
    </label>
    <label class="full">سبب الانتقال <textarea name="reason" rows="4"></textarea></label>
    <div class="form-actions">
        <button class="primary" type="submit"><i data-lucide="save"></i><span>تنفيذ</span></button>
    </div>
</form>
@endsection
