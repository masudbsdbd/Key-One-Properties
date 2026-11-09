<form method="POST" action="{{ route('refunds.approve', $refund) }}" data-ajax-form data-reload-on-success class="space-y-5" data-refund-deduction data-amount="{{ $refund->amount }}">
    @csrf
    <div class="flex items-center justify-between rounded-card bg-surface-muted px-4 py-3 text-sm"><span class="text-ink-500">{{ $refund->customer->full_name }}</span><x-money :value="$refund->amount" class="font-semibold text-ink-900" /></div>
    <x-form.input name="deduction_percent" label="Deduction %" value="0" inputmode="decimal" required data-deduction-percent />
    <dl class="space-y-2 text-sm">
        <div class="flex justify-between"><dt class="text-ink-500">Requested</dt><dd><x-money :value="$refund->amount" /></dd></div>
        <div class="flex justify-between"><dt class="text-ink-500">Deduction</dt><dd class="tabular-nums" data-deduction-amount>0.00</dd></div>
        <div class="flex justify-between border-t border-line pt-2 font-semibold"><dt>Refund</dt><dd class="tabular-nums" data-deduction-net>{{ number_format((float) $refund->amount, 2) }}</dd></div>
    </dl>
    <x-form.actions class="mt-0"><x-button variant="secondary" data-modal-close>Cancel</x-button><x-button type="submit" icon="check">Approve</x-button></x-form.actions>
</form>
