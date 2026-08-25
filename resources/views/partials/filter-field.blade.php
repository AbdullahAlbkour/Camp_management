{{-- One control of the filter bar, driven by its declaration in Filter::fields(). --}}
@php
    $name = $field['name'];
    $type = $field['type'] ?? 'text';
    $value = request($name);
    $classes = 'filter-field'.($field['wide'] ?? false ? ' wide' : '').($field['narrow'] ?? false ? ' narrow' : '');
@endphp

<label class="{{ $classes }}">
    <span>{{ $field['label'] }}</span>

    @if ($type === 'select')
        <select name="{{ $name }}">
            <option value="">{{ $field['placeholder'] ?? 'الكل' }}</option>
            @foreach ($field['options'] ?? [] as $key => $label)
                <option value="{{ $key }}" @selected((string) $value === (string) $key)>{{ $label }}</option>
            @endforeach
        </select>
    @elseif ($type === 'toggle')
        <span class="filter-toggle-row">
            <input type="hidden" name="{{ $name }}" value="">
            <input type="checkbox" name="{{ $name }}" value="1" @checked(request()->boolean($name))>
            <span>نعم</span>
        </span>
    @elseif ($type === 'number')
        <input type="number" name="{{ $name }}" value="{{ $value }}"
               placeholder="{{ $field['placeholder'] ?? '' }}"
               @isset($field['min']) min="{{ $field['min'] }}" @endisset
               @isset($field['max']) max="{{ $field['max'] }}" @endisset>
    @else
        <input type="search" name="{{ $name }}" value="{{ $value }}"
               placeholder="{{ $field['placeholder'] ?? '' }}" autocomplete="off">
    @endif
</label>
