@php($canManage = auth()->user()->can('manageMedia', $project))
@if ($media->isEmpty())
    <x-empty-state icon="image" title="No images yet" />
@else
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
        @foreach ($media as $item)
            @php($url = route('projects.media.show', [$project, $item]))
            <figure class="group overflow-hidden rounded-card border border-line bg-surface">
                <a href="{{ $url }}" target="_blank" rel="noopener" class="relative block aspect-[4/3] bg-surface-muted">
                    <img src="{{ $url }}" alt="{{ $item->caption ?? '' }}" class="h-full w-full object-cover" loading="lazy">
                    <span class="absolute left-2 top-2 flex gap-1">
                        @if ($project->cover_media_id === $item->id)<x-badge tone="warning" class="shadow-card">Cover</x-badge>@endif
                        @if ($item->visibility === 'investor')<x-badge tone="violet" class="shadow-card">Investor</x-badge>@endif
                    </span>
                </a>
                <figcaption class="flex items-center gap-2 px-3 py-2">
                    <span class="min-w-0 flex-1 truncate text-[13px] text-ink-700">{{ $item->caption ?: $item->original_name }}</span>
                    @if ($canManage)
                        @if ($project->cover_media_id !== $item->id)
                            <button type="button" class="btn btn-ghost btn-icon h-8 w-8" data-action="post" data-url="{{ route('projects.media.cover', [$project, $item]) }}" aria-label="Set as cover"><x-icon name="image" size="h-4 w-4" /></button>
                        @endif
                        <button type="button" class="btn btn-ghost btn-icon h-8 w-8" data-remote-modal="{{ route('projects.media.edit', [$project, $item]) }}" data-size="max-w-md" aria-label="Edit image"><x-icon name="edit" size="h-4 w-4" /></button>
                        <button type="button" class="btn btn-ghost btn-icon h-8 w-8 text-danger-600" data-action="delete" data-url="{{ route('projects.media.destroy', [$project, $item]) }}" data-confirm="Delete this image?" data-confirm-text="Delete" aria-label="Delete image"><x-icon name="trash" size="h-4 w-4" /></button>
                    @endif
                </figcaption>
            </figure>
        @endforeach
    </div>
@endif
