@extends('layouts.app', ['title' => 'البحث'])

@section('content')
<form method="get" action="{{ route('search.index') }}" class="filters">
    <input type="search" name="q" value="{{ $term }}" placeholder="اسم، رقم وثيقة، هاتف، رمز أسرة أو وحدة..." autofocus>
    <button class="primary" type="submit"><i data-lucide="search"></i><span>بحث</span></button>
</form>

@if ($term === '')
    <section class="panel"><p class="muted">اكتب كلمة للبحث في اللاجئين والأسر والوحدات السكنية والمخيمات.</p></section>
@elseif ($groups->isEmpty())
    <section class="panel"><p class="muted">لا توجد نتائج مطابقة لـ «{{ $term }}».</p></section>
@else
    @foreach ($groups as $group)
        <section class="panel search-group">
            <h3><i data-lucide="{{ $group['icon'] }}"></i><span>{{ $group['label'] }}</span></h3>
            <ul class="search-results">
                @foreach ($group['items'] as $item)
                    <li>
                        <a href="{{ $item['url'] }}">
                            <strong>{{ $item['title'] }}</strong>
                            <span>{{ $item['subtitle'] }}</span>
                            <em>{{ $item['meta'] }}</em>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
@endif
@endsection
