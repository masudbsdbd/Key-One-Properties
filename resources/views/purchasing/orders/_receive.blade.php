@php($today = now(config('app.display_timezone'))->toDateString())
<form method="POST" action="{{ route('purchase-orders.receive', $order) }}" data-ajax-form data-reload-on-success class="space-y-5">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    <div class="grid gap-5 sm:grid-cols-3">
        <x-form.input name="received_at" type="date" label="Received On" :value="$today" :max="$today" required />
        <x-form.input name="supplier_challan_no" label="Supplier Challan No." />
        <div><p class="form-label">Warehouse</p><p class="flex h-10 items-center text-sm text-ink-900">{{ $order->warehouse->name }}</p></div>
    </div>
    <div class="overflow-x-auto rounded-card border border-line">
        <table class="data-table">
            <thead><tr><th>Material</th><th class="text-right">Remaining</th><th class="w-36">Received</th><th class="w-36">Accepted</th></tr></thead>
            <tbody>
                @foreach (App\Support\LineKeys::ordered($order->lines) as $line)
                    @continue(! App\Support\Money::isPositive($line->remaining()))
                    <tr>
                        <td><p class="font-medium text-ink-900">{{ $line->material->name }}</p><p class="text-[13px] text-ink-500">{{ $line->material->unit->code }}</p></td>
                        <td class="text-right tabular-nums">{{ App\Support\Format::qty($line->remaining()) }}</td>
                        <td data-field><input name="quantities[{{ $loop->index }}][received]" class="form-control h-9" inputmode="decimal" placeholder="0" aria-label="Received quantity for {{ $line->material->name }}" data-receive-qty><p class="form-error" data-error hidden></p></td>
                        <td data-field><input name="quantities[{{ $loop->index }}][accepted]" class="form-control h-9" inputmode="decimal" placeholder="0" aria-label="Accepted quantity for {{ $line->material->name }}" data-accept-qty><p class="form-error" data-error hidden></p></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="form-error" data-field-error="quantities" hidden></p>
    <x-form.textarea name="notes" label="Notes" rows="2" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Post Receipt</x-button>
    </x-form.actions>
</form>
