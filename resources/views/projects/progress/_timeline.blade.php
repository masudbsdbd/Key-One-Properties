@if ($updates->isEmpty())
    <x-empty-state icon="activity" title="No progress recorded" />
@else
    <ol class="divide-y divide-line">
        @foreach ($updates as $update)
            <li class="px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-ink-900">{{ $update->stage?->name ?? 'Project' }}</p>
                        <p class="text-[13px] text-ink-500">{{ App\Support\Format::date($update->report_date) }} · {{ $update->recorder?->name }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        @if ($update->visibility === 'investor')<x-badge tone="violet">Shown to Investors</x-badge>@endif
                        <x-badge tone="accent">{{ App\Support\Format::percent($update->previous_percent) }} → {{ App\Support\Format::percent($update->progress_percent) }}</x-badge>
                    </div>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm text-ink-700">{{ $update->summary }}</p>
            </li>
        @endforeach
    </ol>
@endif
