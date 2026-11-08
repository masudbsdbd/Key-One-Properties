@php($q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.') ?: '0')
<div class="overflow-hidden rounded-card border {{ $rows->every('enough') ? 'border-line' : 'border-danger-500/40' }}">
    <div class="flex items-center justify-between gap-3 border-b border-line bg-surface-muted px-4 py-3">
        <p class="text-sm font-semibold text-ink-900">{{ $source->name }} <x-icon name="arrow-right" size="inline h-4 w-4 text-ink-400" /> {{ $destination->name }}</p>
        @if ($rows->every('enough'))<x-badge tone="success">Ready</x-badge>@else<x-badge tone="danger">Not enough stock</x-badge>@endif
    </div>
    <table class="data-table">
        <thead><tr><th>Material</th><th class="text-right">Qty</th><th class="text-right">Source After</th><th class="text-right">Destination After</th>@if ($showValue)<th class="text-right">Value Moved</th>@endif</tr></thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['material']->name }} <span class="text-ink-500">({{ $row['material']->unit->code }})</span></td>
                    <td class="text-right tabular-nums">{{ $q($row['quantity']) }}</td>
                    <td class="text-right tabular-nums {{ $row['enough'] ? '' : 'font-semibold text-danger-600' }}">{{ $q($row['after_source']) }}</td>
                    <td class="text-right tabular-nums">{{ $q($row['after_destination']) }}</td>
                    @if ($showValue)<td class="text-right">{{ $row['value'] === null ? '—' : App\Support\Format::money($row['value']) }}</td>@endif
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="border-t border-line px-4 py-2 text-[13px] text-ink-500">Final values are taken at posting time.</p>
</div>
