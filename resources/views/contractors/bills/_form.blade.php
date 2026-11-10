@php($today = now(config('app.display_timezone'))->toDateString())
<form method="POST" action="{{ route('work-orders.bills.store', $order) }}" data-ajax-form class="space-y-5" data-cbill-form data-retention="{{ $order->retention_percent }}">
    @csrf
    <div>
        <p class="form-label">Approved Measurements</p>
        <div class="max-h-64 overflow-y-auto rounded-card border border-line">
            <table class="data-table">
                <thead><tr><th class="w-10"><input type="checkbox" class="form-check" data-cbill-all @checked(! $selected || $measurements->count() === 1) aria-label="Select all"></th><th>Work Done</th><th class="text-right">Qty</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    @foreach ($measurements as $m)
                        <tr>
                            <td><input type="checkbox" name="measurements[]" value="{{ $m->public_id }}" class="form-check" data-cbill-line data-amount="{{ $m->measured_amount }}" @checked(! $selected || $selected === $m->public_id) aria-label="Bill {{ $m->description }}"></td>
                            <td><p class="text-ink-900">{{ $m->description }}</p><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($m->measurement_date) }}</p></td>
                            <td class="whitespace-nowrap text-right tabular-nums">{{ App\Support\Format::qty($m->quantity) }} {{ $m->unit }}</td>
                            <td class="text-right"><x-money :value="$m->measured_amount" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="form-error" data-field-error="measurements" hidden></p>
    </div>

    <div class="grid gap-5 sm:grid-cols-3">
        <x-form.input name="bill_date" type="date" label="Bill Date" :value="$today" :max="$today" required />
        <x-form.input name="advance_adjustment" label="Advance Adjustment" value="0" inputmode="decimal" :hint="'Available '.App\Support\Format::money($advance)" data-cbill-deduct :disabled="! App\Support\Money::isPositive($advance)" data-disabled-reason="No advance available" />
        <x-form.input name="retention_amount" :label="'Retention ('.rtrim(rtrim($order->retention_percent, '0'), '.').'%)'" inputmode="decimal" data-cbill-deduct data-cbill-retention />
        <x-form.input name="other_deductions" label="Other Deductions" inputmode="decimal" data-cbill-deduct />
        <x-form.input name="deduction_note" label="Deduction For" wrapper-class="sm:col-span-2" />
    </div>

    <dl class="flex flex-wrap items-baseline justify-end gap-x-8 gap-y-2 rounded-card bg-surface-muted px-4 py-3 text-sm">
        <div class="flex items-baseline gap-2"><dt class="text-ink-500">Gross</dt><dd class="tabular-nums text-ink-900" data-cbill-gross>৳ 0.00</dd></div>
        <div class="flex items-baseline gap-2"><dt class="font-medium text-ink-900">Net Payable</dt><dd class="text-base font-semibold tabular-nums text-ink-900" data-cbill-net>৳ 0.00</dd></div>
    </dl>

    <x-form.textarea name="notes" label="Notes" rows="2" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save Draft</x-button>
    </x-form.actions>
</form>
