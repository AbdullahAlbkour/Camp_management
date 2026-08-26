{{--
    Shared filter bar, rendered from a Filter's fields() declaration.

    Expects:
      $filter    App\Filters\Filter
      $route     route name the form submits to
      $total     result count, shown so an empty result is obviously a filter effect
--}}
@php
    $fields = $filter->fields();
    $active = $filter->active(request());
    $hasActive = count($active) > 0;
    $advanced = collect($fields)->filter(fn ($f) => ($f['advanced'] ?? ! in_array($f['name'], ['q', 'camp_id'], true)));
    $primary = collect($fields)->reject(fn ($f) => $advanced->contains($f));
@endphp

<form method="get" action="{{ route($route) }}" class="filters filter-bar" data-filter-bar>
    <div class="filter-primary">
        @foreach ($primary as $field)
            @include('partials.filter-field', ['field' => $field])
        @endforeach

        <button class="primary" type="submit"><i data-lucide="search"></i><span>بحث</span></button>

        <button class="secondary filter-toggle" type="button" data-filter-toggle
                aria-expanded="{{ $hasActive ? 'true' : 'false' }}">
            <i data-lucide="sliders-horizontal"></i>
            <span>فلترة متقدمة</span>
            @if ($advanced->first(fn ($f) => filled(request($f['name']))))
                <span class="filter-dot" title="يوجد فلاتر متقدمة مطبقة"></span>
            @endif
        </button>

        @if ($hasActive)
            <a class="secondary" href="{{ route($route) }}"><i data-lucide="x"></i><span>مسح الكل</span></a>
        @endif
    </div>

    <div class="filter-advanced" data-filter-advanced @unless($hasActive) hidden @endunless>
        @foreach ($advanced as $field)
            @include('partials.filter-field', ['field' => $field])
        @endforeach

        <label class="filter-field narrow">
            <span>عدد الصفوف</span>
            <select name="per_page">
                @foreach ([20, 50, 100] as $size)
                    <option value="{{ $size }}" @selected((int) request('per_page', 20) === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </label>
    </div>

    {{-- Sorting rides along with the filters so it survives a re-search. --}}
    <input type="hidden" name="sort" value="{{ request('sort') }}">
    <input type="hidden" name="dir" value="{{ request('dir') }}">
</form>

@if ($hasActive)
    <div class="filter-chips">
        <span class="filter-chips-label">الفلاتر المطبقة:</span>
        @foreach ($active as $key => $chip)
            <a class="filter-chip" href="{{ route($route, collect(request()->query())->except($key, 'page')->all()) }}"
               title="إزالة هذا الفلتر">
                <span>{{ $chip['label'] }}: <strong>{{ $chip['value'] }}</strong></span>
                <i data-lucide="x"></i>
            </a>
        @endforeach
        <span class="filter-chips-count">{{ number_format($total) }} نتيجة</span>
    </div>
@endif
