@extends('layouts.app', ['title' => 'سجل التدقيق'])

@section('content')
<form method="get" class="filters">
    <select name="user_id">
        <option value="">كل المستخدمين</option>
        @foreach ($users as $id => $name)
            <option value="{{ $id }}" @selected(request('user_id') == $id)>{{ $name }}</option>
        @endforeach
    </select>
    <select name="module">
        <option value="">كل الموديولات</option>
        @foreach ($modules as $module)
            <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
        @endforeach
    </select>
    <select name="sensitivity">
        <option value="">كل مستويات الحساسية</option>
        @foreach ($sensitivities as $value => $label)
            <option value="{{ $value }}" @selected(request('sensitivity') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <input name="action" value="{{ request('action') }}" placeholder="نوع العملية">
    <input type="date" name="from" value="{{ request('from') }}">
    <input type="date" name="to" value="{{ request('to') }}">
    <button class="secondary" type="submit"><i data-lucide="filter"></i><span>تصفية</span></button>
</form>

<section class="table-wrap">
    <table>
        <thead><tr><th>الوقت</th><th>المستخدم</th><th>الموديول</th><th>العملية</th><th>الحساسية</th><th>الوصف</th></tr></thead>
        <tbody>
            @forelse ($rows as $log)
                <tr>
                    <td>{{ $log->created_at }}</td>
                    <td>{{ $log->user?->name ?? '-' }}</td>
                    <td>{{ $log->module }}</td>
                    <td>{{ $log->action }}</td>
                    <td><span class="badge sensitivity-{{ $log->sensitivity }}">{{ $sensitivities[$log->sensitivity] ?? $log->sensitivity }}</span></td>
                    <td>{{ $log->description }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">لا توجد سجلات.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>

{{ $rows->links() }}
@endsection
