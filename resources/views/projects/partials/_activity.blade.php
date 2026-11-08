@if ($entries->isEmpty())
    <x-empty-state icon="activity" title="No activity yet" />
@else
    <ol class="divide-y divide-line">
        @foreach ($entries as $entry)
            @php($changes = App\Support\ActivityPresenter::changes($entry))
            <li class="flex gap-4 px-5 py-3.5">
                <x-avatar :name="$entry->causer?->name ?? 'System'" size="h-8 w-8 text-[11px]" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-ink-900"><span class="font-medium">{{ $entry->causer?->name ?? 'System' }}</span> · {{ App\Support\ActivityPresenter::event($entry) }} · <span class="text-ink-600">{{ App\Support\ActivityPresenter::subject($entry) }}</span></p>
                    @if ($changes)
                        <p class="mt-0.5 text-[13px] text-ink-500">
                            @foreach (array_slice($changes, 0, 4) as $change){{ $change['field'] }}: {{ $change['old'] }} → {{ $change['new'] }}@if (! $loop->last) · @endif @endforeach
                        </p>
                    @endif
                </div>
                <time class="shrink-0 text-[13px] text-ink-500">{{ App\Support\Format::datetime($entry->created_at) }}</time>
            </li>
        @endforeach
    </ol>
@endif
