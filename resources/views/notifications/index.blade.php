@extends('layouts.app', ['title' => 'التنبيهات'])

@section('content')
<section class="toolbar">
    <div>
        <h2>التنبيهات</h2>
        <p>{{ number_format($rows->total()) }} تنبيه</p>
    </div>
</section>

<section class="notification-list">
    @forelse ($rows as $notification)
        <article class="notification-item {{ $notification->status }}">
            <div>
                <strong>{{ $notification->title }}</strong>
                <p>{{ $notification->body }}</p>
                <small>{{ $notification->type }} / {{ $notification->status }} / {{ $notification->created_at }}</small>
            </div>
            <div class="actions">
                @if ($notification->status === 'unread')
                    <form method="post" action="{{ route('notifications.read', $notification) }}">@csrf<button class="secondary" type="submit"><i data-lucide="check"></i><span>مقروء</span></button></form>
                @endif
                @if ($notification->status !== 'resolved')
                    <form method="post" action="{{ route('notifications.resolve', $notification) }}">@csrf<button class="primary" type="submit"><i data-lucide="check-check"></i><span>معالجة</span></button></form>
                @endif
            </div>
        </article>
    @empty
        <div class="empty">لا توجد تنبيهات.</div>
    @endforelse
</section>

{{ $rows->links() }}
@endsection
