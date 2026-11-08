{{-- Generic form for actions that need a reason (reject, reverse, cancel). --}}
<form method="POST" action="{{ $action }}" data-ajax-form @if ($reload ?? false) data-reload-on-success @endif class="space-y-5">
    @csrf
    @if (($method ?? 'POST') !== 'POST') @method($method) @endif
    @if (! empty($summary))
        <div class="rounded-card border {{ ($tone ?? 'danger') === 'danger' ? 'border-danger-500/30 bg-danger-50 text-danger-600' : 'border-line bg-surface-muted text-ink-700' }} px-4 py-3 text-sm">{{ $summary }}</div>
    @endif
    <x-form.textarea name="reason" label="{{ $label ?? 'Reason' }}" rows="3" :required="$required ?? true" autofocus />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" :variant="($tone ?? 'danger') === 'danger' ? 'danger' : 'primary'">{{ $button }}</x-button>
    </x-form.actions>
</form>
