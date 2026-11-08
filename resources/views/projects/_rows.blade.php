@forelse ($projects as $project)
    <tr>
        <td>
            <a href="{{ $project->trashed() ? '#' : route('projects.show', $project) }}" class="flex items-center gap-3 {{ $project->trashed() ? 'pointer-events-none' : '' }}">
                @if ($project->cover)
                    <img src="{{ route('projects.media.show', [$project, $project->cover]) }}" alt="" class="h-10 w-10 rounded-lg object-cover" loading="lazy">
                @else
                    <span class="icon-tile icon-tile-sm tone-accent h-10 w-10"><x-icon name="building" size="h-5 w-5" /></span>
                @endif
                <span class="min-w-0">
                    <span class="block truncate font-medium text-ink-900">{{ $project->name }}</span>
                    <span class="block text-[13px] text-ink-500">{{ $project->code }}</span>
                </span>
            </a>
        </td>
        <td class="text-ink-600">{{ $project->location_text }}</td>
        <td><x-badge :tone="App\Models\Project::STATUS_TONES[$project->status] ?? 'neutral'">{{ App\Models\Project::STATUSES[$project->status] ?? $project->status }}</x-badge></td>
        <td><x-progress :value="$project->overallProgress()" /></td>
        <td class="whitespace-nowrap text-ink-600">{{ App\Support\Format::date($project->start_date) }}</td>
        <td class="whitespace-nowrap text-ink-600">{{ App\Support\Format::date($project->target_completion_date) }}</td>
        <td class="whitespace-nowrap text-right tabular-nums">
            @if ($project->trashed())
                <button type="button" class="btn btn-sm btn-secondary" data-row-action="post" data-url="{{ route('projects.restore', $project->public_id) }}" data-confirm="Restore {{ $project->name }}?" data-tone="primary" data-confirm-text="Restore"><x-icon name="restore" size="h-4 w-4" />Restore</button>
            @else
                {{ App\Support\Format::money($project->estimated_budget, false) }}
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="7"><x-empty-state icon="building" title="No projects found" /></td></tr>
@endforelse
