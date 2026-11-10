@php $canManage = auth()->user()->can('construction:manage', $project); @endphp
@if ($milestones->isEmpty())
    <x-empty-state icon="check-square" title="No milestones yet" />
@else
    @php
        $done = $milestones->where('status', 'completed')->count();
        $late = $milestones->filter->isOverdue()->count();
        $next = $milestones->first(fn ($m) => $m->status !== 'completed' && ! $m->isOverdue());
    @endphp
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-line px-5 py-3 text-[13px] text-ink-600">
        <span><span class="font-semibold text-ink-900">{{ $done }}</span> of {{ $milestones->count() }} done</span>
        @if ($late)<span class="font-medium text-danger-600">{{ $late }} late</span>@endif
        @if ($next)<span class="min-w-0 truncate">Next: <span class="font-medium text-ink-900">{{ $next->title }}</span> · {{ $next->timing()['label'] }}</span>@endif
    </div>
    <ul class="divide-y divide-line">
        @foreach ($milestones as $milestone)
            <li class="flex items-center gap-4 px-5 py-3.5">
                <span @class(['icon-tile icon-tile-sm', 'tone-success' => $milestone->status === 'completed', 'tone-danger' => $milestone->isOverdue(), 'tone-accent' => $milestone->status !== 'completed' && ! $milestone->isOverdue()])>
                    <x-icon :name="$milestone->status === 'completed' ? 'check' : 'calendar'" size="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-ink-900">{{ $milestone->title }}</p>
                    <p class="text-[13px] text-ink-500">
                        Plan {{ App\Support\Format::date($milestone->planned_at) }}
                        @if ($milestone->completed_at) · Done {{ App\Support\Format::date($milestone->completed_at) }}@endif
                        @if ($milestone->workPackage) · {{ $milestone->workPackage->code }}@endif
                    </p>
                </div>
                @php $timing = $milestone->timing(); @endphp
                <x-badge :tone="$timing['tone']" class="shrink-0">{{ $timing['label'] }}</x-badge>
                @if ($canManage)
                    @if ($milestone->status !== 'completed')
                        <button type="button" class="btn btn-ghost btn-icon text-success-600" data-action="post" data-url="{{ route('construction.milestones.complete', [$project, $milestone]) }}" data-confirm="Mark {{ $milestone->title }} done today?" data-confirm-text="Mark Done" data-tone="primary" aria-label="Mark {{ $milestone->title }} done"><x-icon name="check" size="h-4 w-4" /></button>
                    @endif
                    <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('construction.milestones.edit', [$project, $milestone]) }}" aria-label="Edit {{ $milestone->title }}"><x-icon name="edit" size="h-4 w-4" /></button>
                    <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-action="delete" data-url="{{ route('construction.milestones.destroy', [$project, $milestone]) }}" data-confirm="Delete {{ $milestone->title }}?" data-confirm-text="Delete" aria-label="Delete {{ $milestone->title }}"><x-icon name="trash" size="h-4 w-4" /></button>
                @endif
            </li>
        @endforeach
    </ul>
@endif
