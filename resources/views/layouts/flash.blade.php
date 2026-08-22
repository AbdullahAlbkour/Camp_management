@if (session('success'))
    <div class="alert success">{{ session('success') }}</div>
@endif

@if (session('warning'))
    <div class="alert warning">{{ session('warning') }}</div>
@endif

@if ($errors->any())
    <div class="alert danger">
        <strong>تحقق من البيانات المدخلة.</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
