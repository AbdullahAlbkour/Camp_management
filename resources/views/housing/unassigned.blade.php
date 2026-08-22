@extends('layouts.app', ['title' => 'لاجئون بدون سكن'])

@section('content')
<section class="toolbar">
    <div>
        <h2>لاجئون بدون سكن</h2>
        <p>{{ number_format($rows->total()) }} سجل</p>
    </div>
</section>

<section class="table-wrap">
    <table>
        <thead><tr><th>الاسم</th><th>المخيم</th><th>الوجود</th><th>إجراءات</th></tr></thead>
        <tbody>
            @forelse ($rows as $refugee)
                <tr>
                    <td>{{ $refugee->full_name }}</td>
                    <td>{{ $refugee->currentCamp?->name }}</td>
                    <td>{{ $refugee->presence_status }}</td>
                    <td class="actions">
                        <a class="icon-link" href="{{ route('housing.transfer.form', $refugee) }}" title="تخصيص سكن"><i data-lucide="bed"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">لا توجد حالات بدون سكن.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>

{{ $rows->links() }}
@endsection
