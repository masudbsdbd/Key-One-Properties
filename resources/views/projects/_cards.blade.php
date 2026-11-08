<tr>
    <td colspan="7" class="p-4">
        @if ($projects->isEmpty())
            <x-empty-state icon="building" title="No projects found" />
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($projects as $project)
                    <article class="overflow-hidden rounded-card border border-line bg-surface shadow-card">
                        <a href="{{ $project->trashed() ? '#' : route('projects.show', $project) }}" class="block">
                            <div class="relative h-40 bg-sidebar">
                                @if ($project->cover)
                                    <img src="{{ route('projects.media.show', [$project, $project->cover]) }}" alt="" class="h-full w-full object-cover" loading="lazy">
                                @else
                                    <div class="flex h-full items-center justify-center"><x-brand-mark size="h-16 w-16" class="opacity-60" /></div>
                                @endif
                                <x-badge :tone="App\Models\Project::STATUS_TONES[$project->status] ?? 'neutral'" class="absolute left-3 top-3 shadow-card">{{ App\Models\Project::STATUSES[$project->status] ?? $project->status }}</x-badge>
                            </div>
                            <div class="space-y-3 p-4">
                                <div>
                                    <p class="truncate font-semibold text-ink-900">{{ $project->name }}</p>
                                    <p class="flex items-center gap-1.5 text-[13px] text-ink-500"><x-icon name="map-pin" size="h-3.5 w-3.5" />{{ $project->location_text }}</p>
                                </div>
                                <x-progress :value="$project->overallProgress()" />
                                <div class="flex justify-between text-[13px] text-ink-500">
                                    <span>{{ $project->code }}</span>
                                    <span>{{ App\Support\Format::date($project->target_completion_date) }}</span>
                                </div>
                            </div>
                        </a>
                    </article>
                @endforeach
            </div>
        @endif
    </td>
</tr>
