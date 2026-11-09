@php($showValue = auth()->user()->can('materials.valuation.view'))
@forelse ($transfers as $transfer)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('transfers.show', $transfer) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $transfer->transfer_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($transfer->created_at) }}</p></td>
        <td class="text-ink-700">{{ $transfer->sourceWarehouse->name }}<x-ref :to="$transfer->sourceWarehouse->project" class="block text-[13px] text-ink-400">{{ $transfer->sourceWarehouse->project?->code ?? 'Company' }}</x-ref></td>
        <td class="text-ink-700">{{ $transfer->destinationWarehouse->name }}<x-ref :to="$transfer->destinationWarehouse->project" class="block text-[13px] text-ink-400">{{ $transfer->destinationWarehouse->project?->code ?? 'Company' }}</x-ref></td>
        <td>{{ App\Models\MaterialTransfer::KINDS[$transfer->kind] }}@if ($transfer->borrow)<x-ref :to="$transfer->borrow" class="block text-[13px] text-ink-500">{{ $transfer->borrow->borrow_no }}</x-ref>@endif</td>
        <td class="text-right">@if ($showValue && $transfer->status === 'posted' || $showValue && $transfer->status === 'reversed')<x-money :value="App\Support\Money::add(...$transfer->lines->map(fn ($l) => (string) ($l->total_value ?? '0'))->all())" />@else<span class="text-ink-400">—</span>@endif</td>
        <td><x-status-badge :status="$transfer->status" /></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="arrow-right" title="No transfers" /></td></tr>
@endforelse
