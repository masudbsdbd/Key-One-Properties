@php
    $canManage = auth()->user()->can('manageStages', $project);
    $canProgress = auth()->user()->can('updateProgress', $project);
    $packages = App\Models\WorkPackage::query()->where('project_id', $project->id)->whereNotNull('stage_id')->orderBy('code')->get(['stage_id', 'parent_id', 'code', 'progress_percent']);
    $packaged = $packages->pluck('stage_id')->flip();
    $topLevel = $packages->whereNull('parent_id')->groupBy('stage_id');
@endphp
@if ($project->stages->isEmpty())
    <x-empty-state icon="layers" title="No stages yet" />
@else
    <ol class="divide-y divide-line" @if ($canManage) data-sortable-stages @endif>
        @foreach ($project->stages as $stage)
            <li class="flex flex-col gap-3 bg-surface px-5 py-4 sm:flex-row sm:items-center" data-stage-id="{{ $stage->public_id }}">
                @if ($canManage)
                    <button type="button" class="btn btn-ghost btn-icon -ml-2 hidden cursor-grab touch-none text-ink-400 active:cursor-grabbing sm:inline-flex" data-drag-handle aria-label="Reorder {{ $stage->name }}. Use arrow keys to move."><x-icon name="drag" size="h-5 w-5" /></button>
                @endif
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold {{ $stage->status === 'completed' ? 'bg-success-50 text-success-600' : 'bg-accent-50 text-accent-700' }}">
                    @if ($stage->status === 'completed')<x-icon name="check" size="h-4 w-4" />@else{{ $loop->iteration }}@endif
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-medium text-ink-900">{{ $stage->name }}</p>
                        <x-badge :tone="App\Models\ProjectStage::STATUS_TONES[$stage->status] ?? 'neutral'">{{ App\Models\ProjectStage::STATUSES[$stage->status] ?? $stage->status }}</x-badge>
                    </div>
                    @php
                        $dates = array_filter([
                            $stage->planned_start_at || $stage->planned_end_at ? 'Plan '.App\Support\Format::date($stage->planned_start_at).' – '.App\Support\Format::date($stage->planned_end_at) : null,
                            $stage->actual_start_at ? 'Started '.App\Support\Format::date($stage->actual_start_at) : null,
                            $stage->actual_end_at ? 'Done '.App\Support\Format::date($stage->actual_end_at) : null,
                        ]);
                        $own = $topLevel[$stage->id] ?? collect();
                    @endphp
                    @if ($dates)<p class="mt-0.5 text-[13px] text-ink-500">{{ implode(' · ', $dates) }}</p>@endif
                    <x-progress :value="$stage->progress_percent" class="mt-2 max-w-md" />
                    @if ($own->isNotEmpty())
                        <a href="#packages" class="mt-1.5 block text-[13px] text-ink-500 hover:text-accent-700">From {{ \Illuminate\Support\Str::plural('work package', $own->count(), true) }}: {{ $own->take(4)->map(fn ($p) => $p->code.' '.App\Support\Format::percent($p->progress_percent))->implode(' · ') }}@if ($own->count() > 4) · +{{ $own->count() - 4 }} more @endif</a>
                    @endif
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    @if ($canProgress)
                        <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('projects.progress.create', [$project, 'stage' => $stage->public_id]) }}" aria-label="Record progress for {{ $stage->name }}" @disabled($packaged->has($stage->id)) data-disabled-reason="Follows its work packages"><x-icon name="plus" size="h-4 w-4" /></button>
                    @endif
                    @if ($canManage)
                        <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('projects.stages.edit', [$project, $stage]) }}" aria-label="Edit {{ $stage->name }}"><x-icon name="edit" size="h-4 w-4" /></button>
                        <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-action="delete" data-url="{{ route('projects.stages.destroy', [$project, $stage]) }}" data-confirm="Delete stage {{ $stage->name }}?" data-confirm-text="Delete" aria-label="Delete {{ $stage->name }}"><x-icon name="trash" size="h-4 w-4" /></button>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
