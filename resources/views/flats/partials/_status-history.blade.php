@if ($history->isEmpty())
    <x-empty-state icon="refresh" title="No status changes" />
@else
    <div class="overflow-x-auto"><table class="data-table">
        <thead><tr><th>Date</th><th>Change</th><th>Reason</th><th>By</th></tr></thead>
        <tbody>
            @foreach ($history as $row)
                <tr>
                    <td class="whitespace-nowrap">{{ App\Support\Format::datetime($row->changed_at) }}</td>
                    <td class="whitespace-nowrap">
                        @if ($row->from_status)<x-badge :tone="App\Models\Flat::STATUS_TONES[$row->from_status] ?? 'neutral'">{{ App\Models\Flat::STATUSES[$row->from_status] ?? $row->from_status }}</x-badge> →@endif
                        <x-badge :tone="App\Models\Flat::STATUS_TONES[$row->to_status] ?? 'neutral'">{{ App\Models\Flat::STATUSES[$row->to_status] ?? $row->to_status }}</x-badge>
                    </td>
                    <td>{{ $row->reason ?: '—' }}</td>
                    <td class="whitespace-nowrap text-ink-600">{{ $row->changer?->name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table></div>
@endif
