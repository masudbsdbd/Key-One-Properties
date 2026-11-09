@if ($attachments->isEmpty())
    <x-empty-state icon="file" title="No files" class="py-8" />
@else
    <ul class="divide-y divide-line">
        @foreach ($attachments as $file)
            <li class="flex items-center gap-3 px-5 py-3">
                @if ($file->isImage())
                    <a href="{{ route('attachments.show', $file) }}" target="_blank" rel="noopener"><img src="{{ route('attachments.show', $file) }}" alt="" class="h-10 w-10 rounded-lg object-cover" loading="lazy"></a>
                @else
                    <span class="icon-tile icon-tile-sm tone-info"><x-icon name="file" size="h-4 w-4" /></span>
                @endif
                <div class="min-w-0 flex-1">
                    <a href="{{ route('attachments.show', $file) }}" target="_blank" rel="noopener" class="block truncate text-sm font-medium text-ink-900 hover:text-accent-700">{{ $file->original_name }}</a>
                    <p class="text-[13px] text-ink-500">@if (($shareable ?? false) && $file->portal_visible)<x-badge tone="info">Portal</x-badge> @endif{{ App\Support\Format::bytes($file->file_size) }} · {{ $file->uploader?->name }} · {{ App\Support\Format::datetime($file->created_at) }}</p>
                </div>
                <a href="{{ route('attachments.show', [$file, 'download' => 1]) }}" class="btn btn-ghost btn-icon" aria-label="Download {{ $file->original_name }}"><x-icon name="download" size="h-4 w-4" /></a>
                @if ($canManage && ($shareable ?? false))
                    <button type="button" class="btn btn-ghost btn-icon" data-action="post" data-url="{{ route('attachments.portal', $file) }}" data-keep-open aria-label="{{ $file->portal_visible ? 'Hide from portal' : 'Share with portal' }}"><x-icon :name="$file->portal_visible ? 'lock' : 'external'" size="h-4 w-4" /></button>
                @endif
                @if ($canManage)
                    <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-action="delete" data-url="{{ route('attachments.destroy', $file) }}" data-keep-open data-confirm="Remove {{ $file->original_name }}?" data-confirm-text="Remove" aria-label="Remove {{ $file->original_name }}"><x-icon name="trash" size="h-4 w-4" /></button>
                @endif
            </li>
        @endforeach
    </ul>
@endif
