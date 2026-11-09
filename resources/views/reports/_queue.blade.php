<form method="POST" action="{{ route('reports.queue', $report->key()) }}" data-ajax-form class="space-y-5">
    @csrf
    @foreach ($filters as $name => $value)@if (is_scalar($value) && $value !== '')<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif @endforeach
    <x-form.select name="format" label="Format" :options="['csv' => 'CSV (Excel)', 'pdf' => 'PDF']" selected="csv" required :enhance="false" />
    <dl class="space-y-1 text-sm">
        @foreach ($report->describe(request(), auth()->user()) as $label => $value)
            <div class="flex justify-between gap-3"><dt class="text-ink-500">{{ $label }}</dt><dd class="text-right text-ink-900">{{ $value }}</dd></div>
        @endforeach
    </dl>
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="download">Queue Export</x-button>
    </x-form.actions>
</form>
