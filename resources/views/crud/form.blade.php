@extends('layouts.app', ['title' => $title])

@section('content')
<section class="toolbar">
    <div>
        <h2>{{ $title }}</h2>
        @isset($hint)<p>{{ $hint }}</p>@endisset
    </div>
    <a class="secondary" href="{{ $backRoute }}"><i data-lucide="arrow-right"></i><span>رجوع</span></a>
</section>

<form method="post" action="{{ $action }}" class="form-grid">
    @csrf
    @if (! in_array($method, ['GET', 'POST'], true))
        @method($method)
    @endif

    @foreach ($fields as $field)
        @php
            $name = $field['name'];
            $type = $field['type'] ?? 'text';
            $value = old($name, $field['value'] ?? data_get($model, $name));
        @endphp

        @if ($type === 'hidden')
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @elseif ($type === 'async-refugee' || $type === 'async-household')
            <label>
                {{ $field['label'] }}
                <input type="hidden" name="{{ $name }}" value="{{ $value }}" @required($field['required'] ?? false)>
                <div class="async-select" data-async-select="{{ $field['url'] }}" data-target="{{ $name }}">
                    <input type="search" value="{{ $field['display'] ?? '' }}" placeholder="{{ $field['placeholder'] ?? 'اكتب للبحث...' }}" autocomplete="off">
                    <div class="async-options" role="listbox"></div>
                </div>
            </label>
        @elseif ($type === 'checkbox')
            <label class="checkbox-row">
                <input type="hidden" name="{{ $name }}" value="0">
                <input type="checkbox" name="{{ $name }}" value="1" @checked((bool) $value)>
                <span>{{ $field['label'] }}</span>
            </label>
        @else
            <label>
                {{ $field['label'] }}
                @if ($type === 'select')
                    <select name="{{ $name }}" class="js-searchable-select" @required($field['required'] ?? false)>
                        <option value="">-- اختر --</option>
                        @foreach ($field['options'] ?? [] as $optionValue => $optionLabel)
                            <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                        @endforeach
                    </select>
                @elseif ($type === 'textarea')
                    <textarea name="{{ $name }}" rows="4" @required($field['required'] ?? false)>{{ $value }}</textarea>
                @else
                    <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" @required($field['required'] ?? false) @if(isset($field['step'])) step="{{ $field['step'] }}" @endif>
                @endif
            </label>
        @endif
    @endforeach

    <div class="form-actions">
        <button class="primary" type="submit"><i data-lucide="save"></i><span>حفظ</span></button>
    </div>
</form>
@endsection
