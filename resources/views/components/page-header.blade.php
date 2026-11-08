@props(['title', 'subtitle' => null, 'breadcrumbs' => []])
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        @if ($breadcrumbs)
            <nav aria-label="Breadcrumb" class="mb-1.5">
                <ol class="flex flex-wrap items-center gap-1 text-[13px] text-ink-500">
                    @foreach ($breadcrumbs as $label => $url)
                        <li class="flex items-center gap-1">
                            @if (! $loop->first)<x-icon name="chevron-right" size="h-3.5 w-3.5" class="text-ink-300" />@endif
                            @if ($url && ! $loop->last)
                                <a href="{{ $url }}" class="hover:text-ink-900">{{ $label }}</a>
                            @else
                                <span @if ($loop->last) aria-current="page" class="text-ink-700" @endif>{{ $label }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1 class="text-2xl font-semibold tracking-tight text-ink-900 truncate">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1 text-sm text-ink-500">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
</div>
