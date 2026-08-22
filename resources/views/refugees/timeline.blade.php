<section class="panel">
    <h2>{{ $title }}</h2>
    <div class="table-wrap embedded">
        <table>
            <thead>
                <tr>
                    @foreach ($fields as $field)
                        <th>{{ str_replace('_', ' ', $field) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($fields as $field)
                            <td>{{ data_get($row, $field) ?? '-' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($fields) }}" class="empty">لا توجد بيانات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
