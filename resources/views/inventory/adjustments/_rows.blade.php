@php($actor = auth()->user())
@forelse ($adjustments as $adjustment)
    <tr>
        <td class="whitespace-nowrap"><p class="font-medium text-ink-900">{{ $adjustment->adjustment_no }}</p><p class="text-[13px] text-ink-500">{{ $adjustment->creator?->name }} · {{ App\Support\Format::date($adjustment->created_at) }}</p></td>
        <td class="text-ink-600">{{ $adjustment->warehouse->name }}</td>
        <td class="text-[13px]">
            @foreach ($adjustment->lines as $line)
                <span class="block whitespace-nowrap"><span class="{{ (float) $line->qty_delta < 0 ? 'text-danger-600' : 'text-success-600' }} font-medium tabular-nums">{{ (float) $line->qty_delta > 0 ? '+' : '' }}{{ rtrim(rtrim($line->qty_delta, '0'), '.') }}</span> {{ $line->material->unit->code }} {{ $line->material->name }}</span>
            @endforeach
        </td>
        <td class="max-w-56 text-ink-600">{{ $adjustment->reason }}@if ($adjustment->rejection_reason)<span class="block text-[13px] text-danger-600">{{ $adjustment->rejection_reason }}</span>@endif</td>
        <td><x-status-badge :status="$adjustment->status" /></td>
        <td class="whitespace-nowrap text-right">
            @if ($adjustment->status === 'submitted' && App\Modules\Inventory\Policies\InventoryAccess::warehouse($actor, $adjustment->warehouse, 'materials.adjust.approve'))
                <x-button size="sm" data-row-action="post" data-url="{{ route('adjustments.approve', $adjustment) }}" data-confirm="Approve and post {{ $adjustment->adjustment_no }}?" data-confirm-message="Stock quantities and value in {{ $adjustment->warehouse->name }} will change." data-tone="primary" data-confirm-text="Approve">Approve</x-button>
                <x-button size="sm" variant="ghost" data-remote-modal="{{ route('adjustments.reject.form', $adjustment) }}" data-size="max-w-md">Reject</x-button>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="edit" title="No adjustments" /></td></tr>
@endforelse
