<x-layouts.error title="Session expired">
    <p class="text-sm font-semibold text-accent-600">419</p>
    <h1 class="mt-2 text-2xl font-semibold text-ink-900">Session expired</h1>
    <div class="mt-8 flex justify-center gap-3">
            <x-button href="{{ url()->previous() }}" variant="secondary">Back</x-button>
        <x-button href="{{ url('/') }}">Go to dashboard</x-button>
    </div>
</x-layouts.error>
