@php($canManage = auth()->user()->can('manageMedia', $flat))
@if ($flat->media->isEmpty())
    <x-empty-state icon="image" title="No images yet" />
@else
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach ($flat->media as $item)
            @php($url = route('flats.media.show', [$flat, $item]))
            <figure class="overflow-hidden rounded-card border border-line">
                <a href="{{ $url }}" target="_blank" rel="noopener" class="block aspect-[4/3] bg-surface-muted"><img src="{{ $url }}" alt="" class="h-full w-full object-cover" loading="lazy"></a>
                <figcaption class="flex items-center gap-2 px-3 py-2">
                    <span class="min-w-0 flex-1 truncate text-[13px] text-ink-700">{{ $item->caption ?: $item->original_name }}</span>
                    @if ($canManage)
                        <button type="button" class="btn btn-ghost btn-icon h-8 w-8 text-danger-600" data-action="delete" data-url="{{ route('flats.media.destroy', [$flat, $item]) }}" data-confirm="Delete this image?" data-confirm-text="Delete" aria-label="Delete image"><x-icon name="trash" size="h-4 w-4" /></button>
                    @endif
                </figcaption>
            </figure>
        @endforeach
    </div>
@endif
