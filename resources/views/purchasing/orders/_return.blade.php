<form method="POST" action="{{ route('purchase-orders.return', [$order, $receipt]) }}" data-ajax-form data-reload-on-success class="space-y-5">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    <div class="overflow-x-auto rounded-card border border-line">
        <table class="data-table">
            <thead><tr><th>Material</th><th class="text-right">Returnable</th><th class="text-right">Unit Cost</th><th class="w-36">Return</th></tr></thead>
            <tbody>
                @foreach (App\Support\LineKeys::ordered($receipt->lines) as $line)
                    @continue(! App\Support\Money::isPositive($line->returnable()))
                    <tr>
                        <td><p class="font-medium text-ink-900">{{ $line->material->name }}</p><p class="text-[13px] text-ink-500">{{ $line->material->unit->code }}@if ($line->billed_bill_id) · <span class="text-violet-600">Billed</span>@endif</p></td>
                        <td class="text-right tabular-nums">{{ App\Support\Format::qty($line->returnable()) }}</td>
                        <td class="text-right"><x-money :value="$line->unit_cost" /></td>
                        <td data-field><input name="quantities[{{ $loop->index }}]" class="form-control h-9" inputmode="decimal" placeholder="0" aria-label="Return quantity for {{ $line->material->name }}"><p class="form-error" data-error hidden></p></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="form-error" data-field-error="quantities" hidden></p>
    <x-form.textarea name="reason" label="Reason" rows="2" required />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" variant="danger" icon="restore">Post Return</x-button>
    </x-form.actions>
</form>
