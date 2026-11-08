<form method="POST" action="{{ route('flats.status.update', $flat) }}" data-ajax-form class="space-y-5">
    @csrf
    <div class="flex items-center justify-between rounded-card bg-surface-muted px-4 py-3 text-sm">
        <span class="text-ink-500">Current status</span>
        <x-badge :tone="App\Models\Flat::STATUS_TONES[$flat->status]">{{ App\Models\Flat::STATUSES[$flat->status] }}</x-badge>
    </div>
    <x-form.select name="status" label="New Status" :options="collect(App\Models\Flat::MANUAL_STATUSES)->reject(fn ($s) => $s === $flat->status)->mapWithKeys(fn ($s) => [$s => App\Models\Flat::STATUSES[$s]])" required />
    <x-form.input name="reason" label="Reason" autofocus />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Update Status</x-button>
    </x-form.actions>
</form>
