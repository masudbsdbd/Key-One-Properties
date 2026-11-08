@php($actor = auth()->user())
@if ($notes->isEmpty())
    <x-empty-state icon="note" title="No notes yet" />
@else
    <ul class="divide-y divide-line">
        @foreach ($notes as $note)
            <li class="flex gap-4 px-5 py-4">
                <x-avatar :name="$note->author?->name ?? '?'" size="h-8 w-8 text-[11px]" />
                <div class="min-w-0 flex-1">
                    <p class="text-[13px] text-ink-500"><span class="font-medium text-ink-800">{{ $note->author?->name }}</span> · {{ App\Support\Format::datetime($note->created_at) }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-ink-800">{{ $note->body }}</p>
                </div>
                @if ($note->created_by === $actor->id || $actor->can('update', $customer))
                    <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-action="delete" data-url="{{ route('customers.notes.destroy', [$customer, $note]) }}" data-confirm="Delete this note?" data-confirm-text="Delete" aria-label="Delete note"><x-icon name="trash" size="h-4 w-4" /></button>
                @endif
            </li>
        @endforeach
    </ul>
@endif
