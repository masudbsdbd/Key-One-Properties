<form method="POST" action="{{ route('accounting.reconciliation.account.store', $event->event_uuid) }}" data-ajax-form class="space-y-5">
    @csrf
    <div class="rounded-card border border-line bg-surface-muted px-4 py-3 text-sm">
        <p class="font-semibold text-ink-900">{{ $label }}@if ($event->amount !== null) · <x-money :value="$event->amount" />@endif</p>
        <p class="text-ink-500">{{ App\Support\Format::date($event->effective_at) }}</p>
    </div>
    <x-form.select name="money_account" id="waiting-account" label="Account" :options="$accounts" required />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Save and Post</x-button>
    </x-form.actions>
</form>
