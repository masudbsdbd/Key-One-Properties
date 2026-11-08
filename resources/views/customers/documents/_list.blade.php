@if ($documents->isEmpty())
    <x-empty-state icon="file" title="No documents" />
@else
    <ul class="divide-y divide-line">
        @foreach ($documents as $document)
            <li class="flex items-center gap-4 px-5 py-3.5">
                <span class="icon-tile icon-tile-sm tone-info"><x-icon name="file" size="h-4 w-4" /></span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-ink-900">{{ $document->original_name }}</p>
                    <p class="text-[13px] text-ink-500">{{ App\Models\CustomerDocument::CATEGORIES[$document->category] ?? $document->category }} · {{ App\Support\Format::bytes($document->file_size) }} · {{ $document->uploader?->name }} · {{ App\Support\Format::datetime($document->created_at) }}</p>
                </div>
                <a href="{{ route('customers.documents.download', [$customer, $document]) }}" class="btn btn-ghost btn-icon" aria-label="Download {{ $document->original_name }}"><x-icon name="download" size="h-4 w-4" /></a>
                <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-action="delete" data-url="{{ route('customers.documents.destroy', [$customer, $document]) }}" data-confirm="Delete {{ $document->original_name }}?" data-confirm-text="Delete" aria-label="Delete document"><x-icon name="trash" size="h-4 w-4" /></button>
            </li>
        @endforeach
    </ul>
@endif
