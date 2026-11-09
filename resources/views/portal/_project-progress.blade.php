<div class="grid gap-6 xl:grid-cols-3">
    <div class="min-w-0 space-y-6 xl:col-span-2">
        <x-card title="Updates" :padded="false">
            @forelse ($updates as $u)
                <div class="border-b border-line px-5 py-4 last:border-0">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="font-medium text-ink-900">{{ $u->workPackage?->name ?? 'Project' }}{{ $u->milestone ? ' · '.$u->milestone->title : '' }}</p>
                        <p class="text-[13px] text-ink-500">{{ App\Support\Format::date($u->report_date) }} · {{ App\Support\Format::percent((float) $u->progress_percent) }}</p>
                    </div>
                    @if ($u->notes)<p class="mt-1 whitespace-pre-line text-sm text-ink-700">{{ $u->notes }}</p>@endif
                    @php $images = $u->attachments->filter(fn ($a) => $a->isImage()); @endphp
                    @if ($images->isNotEmpty())
                        <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
                            @foreach ($images as $img)
                                <a href="{{ route($photoRoute, $img->public_id) }}" target="_blank" rel="noopener" aria-label="Open photo"><img src="{{ route($photoRoute, $img->public_id) }}" alt="" loading="lazy" class="aspect-square w-full rounded-lg object-cover"></a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <x-empty-state icon="activity" title="No published updates" />
            @endforelse
        </x-card>
    </div>
    <div class="min-w-0 space-y-6">
        <x-card title="Stages" :padded="false">
            @forelse ($project->stages as $s)
                <div class="border-b border-line px-5 py-3 last:border-0">
                    <div class="flex items-center justify-between gap-3"><p class="text-sm font-medium text-ink-900">{{ $s->name }}</p><x-badge :tone="App\Models\ProjectStage::STATUS_TONES[$s->status] ?? 'neutral'">{{ App\Models\ProjectStage::STATUSES[$s->status] ?? $s->status }}</x-badge></div>
                    <x-progress :value="$s->progress_percent" class="mt-2" />
                </div>
            @empty
                <x-empty-state icon="layers" title="No stages" />
            @endforelse
        </x-card>
        <x-card title="Milestones" :padded="false">
            @if ($milestones->isEmpty())<x-empty-state icon="check-square" title="No milestones" />@else<x-timeline :items="$milestones->map(fn ($m) => ['title' => $m->title, 'meta' => $m->status === 'completed' ? 'Completed '.App\Support\Format::date($m->completed_at) : 'Planned '.App\Support\Format::date($m->planned_at), 'tone' => $m->status === 'completed' ? 'success' : 'neutral'])" />@endif
        </x-card>
    </div>
</div>
