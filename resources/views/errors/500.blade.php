<x-layouts.error title="Something went wrong">
    <p class="text-sm font-semibold text-accent-600">500</p>
    <h1 class="mt-2 text-2xl font-semibold text-ink-900">Something went wrong</h1>
    <div class="mt-8 flex justify-center gap-3">
        <x-button href="{{ url('/') }}">Go to dashboard</x-button>
    </div>
</x-layouts.error>
