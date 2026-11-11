<form method="POST" action="{{ $type === 'period' ? route('accounting.periods.unlock', $subject) : route('accounting.periods.reopen', $subject) }}" data-ajax-form class="space-y-5">
    @csrf
    <x-form.textarea name="reason" label="Reason" rows="3" required />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">{{ $type === 'period' ? 'Unlock' : 'Reopen Year' }}</x-button>
    </x-form.actions>
</form>
