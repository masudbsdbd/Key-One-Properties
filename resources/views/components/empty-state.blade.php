@props(['icon' => 'info', 'title', 'description' => null])
@php $plain = ! $description && ! trim($slot); @endphp
@if ($plain)
    <div {{ $attributes->merge(['class' => 'flex items-center justify-center gap-3 px-6 py-8 text-center']) }}>
        <span class="icon-tile icon-tile-sm tone-accent"><x-icon :name="$icon" size="h-4 w-4" /></span>
        <p class="text-sm font-medium text-ink-600">{{ $title }}</p>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center px-6 py-12']) }}>
        <span class="icon-tile tone-accent mb-4"><x-icon :name="$icon" size="h-6 w-6" /></span>
        <p class="text-[15px] font-semibold text-ink-900">{{ $title }}</p>
        @if ($description)<p class="mt-1 max-w-sm text-sm text-ink-500">{{ $description }}</p>@endif
        @if (trim($slot))<div class="mt-5 flex gap-2">{{ $slot }}</div>@endif
    </div>
@endif
