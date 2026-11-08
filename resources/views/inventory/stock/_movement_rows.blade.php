@php($q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.'))
@php($actor = auth()->user())
@forelse ($movements as $m)
    @php($ref = $m->reference)
    <tr>
        <td class="whitespace-nowrap text-ink-600">{{ App\Support\Format::datetime($m->posted_at) }}</td>
        <td class="whitespace-nowrap"><p class="font-medium text-ink-900">{{ $m->movement_no }}</p><x-status-badge :status="$m->movement_type === 'reversal' ? 'reversed' : 'posted'" :labels="['reversed' => 'Reversal', 'posted' => App\Models\StockMovement::TYPES[$m->movement_type] ?? $m->movement_type]" /></td>
        <td>{{ $m->material->name }}</td>
        <td class="whitespace-nowrap text-ink-600">{{ $m->fromWarehouse?->code ?? '—' }} <x-icon name="arrow-right" size="inline h-3.5 w-3.5 text-ink-400" /> {{ $m->toWarehouse?->code ?? ($m->movement_type === 'issue' ? 'Consumed' : '—') }}</td>
        <td class="whitespace-nowrap text-right tabular-nums">{{ $q($m->quantity) }} {{ $m->material->unit->code }}</td>
        @if ($showValue)<td class="text-right"><x-money :value="$m->total_value" /></td>@endif
        <td class="whitespace-nowrap text-ink-600">
            @if ($ref instanceof App\Models\MaterialBorrow)<a class="text-accent-700 hover:underline" href="{{ route('borrows.show', $ref) }}">{{ $ref->borrow_no }}</a>
            @elseif ($ref instanceof App\Models\MaterialTransfer)<a class="text-accent-700 hover:underline" href="{{ route('transfers.show', $ref) }}">{{ $ref->transfer_no }}</a>
            @elseif ($ref && method_exists($ref, 'number')){{ $ref->number() }}
            @elseif ($ref){{ $ref->grn_no ?? $ref->return_no ?? '' }}@endif
        </td>
        <td class="text-right">
            @if ($ref instanceof App\Models\StockIssue && $ref->status === 'posted' && $m->movement_type === 'issue' && $actor->can('materials.movement.reverse'))
                <x-button size="sm" variant="ghost" data-remote-modal="{{ route('stock.issues.reverse.form', $ref) }}" data-size="max-w-md">Reverse</x-button>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="8"><x-empty-state icon="list" title="No movements" /></td></tr>
@endforelse
