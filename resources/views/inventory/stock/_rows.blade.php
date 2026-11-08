@php($q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.') ?: '0')
@forelse ($balances as $balance)
    <tr>
        <td><p class="font-medium text-ink-900">{{ $balance->material->name }}</p><p class="text-[13px] text-ink-500">{{ $balance->material->sku }} · {{ $balance->material->unit->code }}</p></td>
        <td class="text-ink-600">{{ $balance->warehouse->name }}<span class="block text-[13px] text-ink-400">{{ $balance->warehouse->project?->code ?? 'Company' }}</span></td>
        <td class="text-right font-medium tabular-nums">{{ $q($balance->owned_qty) }}@if ($balance->isLow()) <x-badge tone="danger" class="ml-1">Low</x-badge>@endif</td>
        <td class="text-right tabular-nums {{ (float) $balance->borrowed_in_qty ? 'text-info-600' : 'text-ink-400' }}">{{ $q($balance->borrowed_in_qty) }}</td>
        <td class="text-right tabular-nums {{ (float) $balance->borrowed_out_qty ? 'text-warning-600' : 'text-ink-400' }}">{{ $q($balance->borrowed_out_qty) }}</td>
        <td class="text-right tabular-nums">{{ $q($balance->physicalQty()) }}</td>
        @if ($showValue)
            <td class="text-right"><x-money :value="$balance->avg_unit_cost" /></td>
            <td class="text-right"><x-money :value="$balance->owned_value" /></td>
        @endif
    </tr>
@empty
    <tr><td colspan="{{ $showValue ? 8 : 6 }}"><x-empty-state icon="layers" title="No stock" /></td></tr>
@endforelse
