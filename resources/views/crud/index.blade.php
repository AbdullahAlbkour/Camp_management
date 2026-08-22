@extends('layouts.app', ['title' => $title])

@section('content')
<section class="toolbar">
    <div>
        <h2>{{ $title }}</h2>
        <p>{{ number_format($rows->total()) }} سجل</p>
    </div>
    @isset($createRoute)
        <a class="primary" href="{{ $createRoute }}"><i data-lucide="plus"></i><span>إضافة</span></a>
    @endisset
</section>

<section class="table-wrap">
    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ $column['label'] }}</th>
                @endforeach
                @if(isset($showRoute) || isset($editRoute))
                    <th>إجراءات</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $column)
                        <td>{{ data_get($row, $column['field']) ?? '-' }}</td>
                    @endforeach
                    @if(isset($showRoute) || isset($editRoute))
                        <td class="actions">
                            @isset($showRoute)
                                <a class="icon-link" href="{{ route($showRoute, $row) }}" title="عرض"><i data-lucide="eye"></i></a>
                            @endisset
                            @isset($editRoute)
                                <a class="icon-link" href="{{ route($editRoute, $row) }}" title="تعديل"><i data-lucide="pencil"></i></a>
                            @endisset
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + 1 }}" class="empty">لا توجد بيانات.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>

{{ $rows->links() }}
@endsection
