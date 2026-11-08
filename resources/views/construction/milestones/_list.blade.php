@php($canManage = auth()->user()->can('construction:manage', $project))
@if ($milestones->isEmpty())
    <x-empty-state icon="check-square" title="No milestones yet" />
@else
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
                @if ($milestone->isOverdue())<x-badge tone="danger">Overdue</x-badge>@else<x-status-badge :status="$milestone->status" />@endif
                @if ($canManage)
                    <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('construction.milestones.edit', [$project, $milestone]) }}" aria-label="Edit {{ $milestone->title }}"><x-icon name="edit" size="h-4 w-4" /></button>
                    <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-action="delete" data-url="{{ route('construction.milestones.destroy', [$project, $milestone]) }}" data-confirm="Delete {{ $milestone->title }}?" data-confirm-text="Delete" aria-label="Delete {{ $milestone->title }}"><x-icon name="trash" size="h-4 w-4" /></button>
                @endif
            </li>
        @endforeach
    </ul>
@endif
