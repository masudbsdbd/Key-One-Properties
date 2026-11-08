<form method="POST" action="{{ route('borrows.settle', [$borrow, $mode]) }}" data-ajax-form data-reload-on-success class="space-y-5">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    @if ($mode === 'convert')
        <div class="rounded-card border border-warning-600/30 bg-warning-50 px-4 py-3 text-sm text-warning-600">
            Ownership of the converted quantity moves from {{ $borrow->sourceProject->name }} to {{ $borrow->destinationProject->name }} at the locked unit cost. This cannot be undone from the borrow.
        </div>
    @endif
    <table class="data-table">
        <thead><tr><th>Material</th><th class="text-right">Outstanding</th>@if ($mode === 'convert')@can('materials.valuation.view')<th class="text-right">Locked Cost</th>@endcan @endif<th class="w-40">{{ $mode === 'return' ? 'Return' : 'Convert' }}</th></tr></thead>
        <tbody>
            @foreach ($borrow->lines->filter(fn ($l) => App\Support\Money::isPositive($l->outstanding())) as $line)
                <tr>
                    <td>{{ $line->material->name }} <span class="text-ink-500">({{ $line->material->unit->code }})</span></td>
                    <td class="text-right tabular-nums">{{ rtrim(rtrim($line->outstanding(), '0'), '.') }}</td>
                    @if ($mode === 'convert')@can('materials.valuation.view')<td class="text-right">{{ App\Support\Format::money($line->locked_unit_cost) }}</td>@endcan @endif
                    <td data-field><input name="quantities[{{ $line->id }}]" class="form-control h-9" inputmode="decimal" placeholder="0" aria-label="Quantity for {{ $line->material->name }}"><p class="form-error" data-error hidden></p></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <x-form.input name="reason" label="{{ $mode === 'convert' ? 'Reason' : 'Note' }}" :required="$mode === 'convert'" />
    <p class="form-error" data-field-error="quantities" hidden></p>
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ $mode === 'return' ? 'Record Return' : 'Convert' }}</x-button>
    </x-form.actions>
</form>
