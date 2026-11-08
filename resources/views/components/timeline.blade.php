@props(['items'])
{{-- items: iterable of ['title' => string, 'meta' => string, 'body' => ?string, 'tone' => ?string] --}}
@php($dots = ['accent' => 'bg-accent-500 ring-accent-100', 'success' => 'bg-success-600 ring-success-50', 'danger' => 'bg-danger-500 ring-danger-50', 'warning' => 'bg-warning-600 ring-warning-50', 'info' => 'bg-info-600 ring-info-50', 'neutral' => 'bg-ink-400 ring-ink-900/5'])
@if (collect($items)->isEmpty())
    <x-empty-state icon="activity" title="No history" class="py-8" />
@else
    <ol class="relative space-y-5 px-5 py-5 before:absolute before:bottom-5 before:left-[29px] before:top-5 before:w-px before:bg-line">
        @foreach ($items as $item)
            <li class="relative flex gap-4">
                <span class="relative z-10 mt-0.5 flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full border-2 border-surface ring-2 {{ $dots[$item['tone'] ?? 'accent'] ?? $dots['accent'] }}"></span>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-ink-900">{{ $item['title'] }}</p>
                    <p class="text-[13px] text-ink-500">{{ $item['meta'] }}</p>
                    @if (! empty($item['body']))<p class="mt-1 whitespace-pre-line text-sm text-ink-700">{{ $item['body'] }}</p>@endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
