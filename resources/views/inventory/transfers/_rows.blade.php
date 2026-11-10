@php
    $showValue = auth()->user()->can('materials.valuation.view');
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.') ?: '0';
@endphp
@forelse ($transfers as $transfer)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('transfers.show', $transfer) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $transfer->transfer_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($transfer->created_at) }} · {{ App\Models\MaterialTransfer::KINDS[$transfer->kind] }}</p>@if ($transfer->borrow)<x-ref :to="$transfer->borrow" class="block text-[13px] text-ink-500">{{ $transfer->borrow->borrow_no }}</x-ref>@endif</td>
        <td class="text-ink-700">{{ $transfer->sourceWarehouse->name }}<x-ref :to="$transfer->sourceWarehouse->project" class="block text-[13px] text-ink-400">{{ $transfer->sourceWarehouse->project?->code ?? 'Company' }}</x-ref></td>
        <td class="text-ink-700">{{ $transfer->destinationWarehouse->name }}<x-ref :to="$transfer->destinationWarehouse->project" class="block text-[13px] text-ink-400">{{ $transfer->destinationWarehouse->project?->code ?? 'Company' }}</x-ref></td>
        <td class="min-w-0 max-w-56">
            @foreach ($transfer->lines->take(3) as $line)<span class="block truncate text-[13px] text-ink-700">{{ $line->material?->name }} <span class="tabular-nums text-ink-500">{{ $qty($line->quantity) }} {{ $line->material?->unit?->code }}</span></span>@endforeach
            @if ($transfer->lines->count() > 3)<span class="block text-[12px] text-ink-500">+{{ $transfer->lines->count() - 3 }} more</span>@endif
        </td>
        <td class="text-right">@if ($showValue && $transfer->status === 'posted' || $showValue && $transfer->status === 'reversed')<x-money :value="App\Support\Money::add(...$transfer->lines->map(fn ($l) => (string) ($l->total_value ?? '0'))->all())" />@else<span class="text-ink-400">—</span>@endif</td>
        <td><x-status-badge :status="$transfer->status" /></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="arrow-right" title="No transfers" /></td></tr>
@endforelse
