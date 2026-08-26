{{--
    A column heading that toggles sorting.
    Expects: $key (whitelisted sort key), $label, $route
--}}
@php
    $current = request('sort');
    $isActive = $current === $key;
    $nextDir = $isActive && request('dir') !== 'asc' ? 'asc' : 'desc';
    $query = collect(request()->query())->except('page')->merge(['sort' => $key, 'dir' => $nextDir])->all();
@endphp
<th class="sortable {{ $isActive ? 'is-sorted' : '' }}">
    <a href="{{ route($route, $query) }}">
        <span>{{ $label }}</span>
        @if ($isActive)
            <i data-lucide="{{ request('dir') === 'asc' ? 'arrow-up' : 'arrow-down' }}"></i>
        @else
            <i data-lucide="chevrons-up-down"></i>
        @endif
    </a>
</th>
