@extends('layouts.app', ['title' => 'الوحدات السكنية'])

@section('content')
<section class="toolbar">
    <div>
        <h2>الوحدات السكنية</h2>
        <p>{{ number_format($rows->total()) }} وحدة</p>
    </div>
    @role('admin','housing_officer')
        <a class="primary" href="{{ route('shelters.create') }}"><i data-lucide="plus"></i><span>إضافة وحدة</span></a>
    @endrole
</section>

@include('partials.filter-bar', ['filter' => $filter, 'route' => 'shelters.index', 'total' => $rows->total()])

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                @include('partials.sortable-header', ['key' => 'code', 'label' => 'الرمز', 'route' => 'shelters.index'])
                <th>المخيم</th>
                <th>النوع</th>
                @include('partials.sortable-header', ['key' => 'capacity', 'label' => 'السعة', 'route' => 'shelters.index'])
                @include('partials.sortable-header', ['key' => 'occupied', 'label' => 'الإشغال', 'route' => 'shelters.index'])
                <th>المتاح</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $shelter)
                @php
                    $available = max(0, (int) $shelter->capacity - (int) $shelter->occupied);
                    $ratio = $shelter->capacity > 0 ? min(100, round($shelter->occupied / $shelter->capacity * 100)) : 0;
                @endphp
                <tr>
                    <td><strong>{{ $shelter->code }}</strong></td>
                    <td>{{ $shelter->camp?->name ?? '-' }}</td>
                    <td>{{ \App\Support\Labels::get('shelter_type', $shelter->type) }}</td>
                    <td>{{ $shelter->capacity }}</td>
                    <td>
                        <span class="occupancy" title="{{ $ratio }}%">
                            <span class="occupancy-bar"><span style="width: {{ $ratio }}%"></span></span>
                            <span class="occupancy-text">{{ $shelter->occupied }}/{{ $shelter->capacity }}</span>
                        </span>
                    </td>
                    <td>
                        @if ($available === 0)
                            <span class="badge unassigned">ممتلئة</span>
                        @else
                            <span class="badge assigned">{{ $available }}</span>
                        @endif
                    </td>
                    <td><span class="badge {{ $shelter->status }}">{{ \App\Support\Labels::get('status', $shelter->status) }}</span></td>
                    <td class="actions">
                        @role('admin','housing_officer')
                            <a class="icon-link" href="{{ route('shelters.edit', $shelter) }}" title="تعديل"><i data-lucide="pencil"></i></a>
                        @endrole
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">لا توجد وحدات مطابقة للفلاتر.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>

{{ $rows->links() }}
@endsection
