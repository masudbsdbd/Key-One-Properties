@php
    $today = App\Support\Dates::today()->toDateString();
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.') ?: '0';
@endphp
<form method="POST" action="{{ route('stock.issues.return', $issue) }}" data-ajax-form class="space-y-5">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    <div class="grid gap-5 sm:grid-cols-3">
        <div><p class="detail-label">Back To</p><p class="detail-value">{{ $issue->warehouse->name }}</p></div>
        <x-form.input name="returned_at" type="date" label="Date" :value="$today" :min="$issue->issued_at->toDateString()" :max="$today" required />
        <x-form.input name="reason" label="Reason" maxlength="255" />
    </div>
    <div class="overflow-x-auto rounded-card border border-line">
        <table class="data-table">
            <thead><tr><th>Material</th><th class="text-right">Issued</th><th class="text-right">Returned</th><th class="text-right">On Site</th><th class="w-40">Return</th></tr></thead>
            <tbody>
                @foreach ($lines as $position => $line)
                    <tr>
                        <td><p class="font-medium text-ink-900">{{ $line->material->name }}</p><p class="text-[13px] text-ink-500">{{ $line->material->unit->code }}</p></td>
                        <td class="text-right tabular-nums">{{ $qty($line->quantity) }}</td>
                        <td class="text-right tabular-nums text-ink-500">{{ $qty($line->returned_qty) }}</td>
                        <td class="text-right font-medium tabular-nums">{{ $qty($line->remaining()) }}</td>
                        <td data-field>
                            <input name="quantities[{{ $position }}]" class="form-control" inputmode="decimal" placeholder="0" aria-label="Return {{ $line->material->name }}"
                                @disabled(! App\Support\Money::isPositive($line->remaining())) data-disabled-reason="Nothing left on site">
                            <p class="form-error" data-error hidden></p>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="form-error" data-field-error="quantities" hidden></p>
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Return</x-button>
    </x-form.actions>
</form>
