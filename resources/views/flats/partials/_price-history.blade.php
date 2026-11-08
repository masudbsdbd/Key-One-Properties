@if ($history->isEmpty())
    <x-empty-state icon="tag" title="No price set" />
@else
    <div class="overflow-x-auto"><table class="data-table">
        <thead><tr><th>Date</th><th class="text-right">Old</th><th class="text-right">New</th><th>Reason</th><th>By</th></tr></thead>
        <tbody>
            @foreach ($history as $row)
                <tr>
                    <td class="whitespace-nowrap">{{ App\Support\Format::datetime($row->effective_at) }}</td>
                    <td class="whitespace-nowrap text-right tabular-nums text-ink-500">{{ App\Support\Format::money($row->old_price, false) }}</td>
                    <td class="whitespace-nowrap text-right font-medium tabular-nums text-ink-900">{{ App\Support\Format::money($row->new_price, false) }}</td>
                    <td class="min-w-40">{{ $row->reason }}</td>
                    <td class="whitespace-nowrap text-ink-600">{{ $row->changer?->name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table></div>
@endif
