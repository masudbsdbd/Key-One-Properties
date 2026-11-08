@forelse ($projects as $project)
    <tr>
        <td>
            <a href="{{ route('construction.show', $project) }}" class="block">
                <span class="block font-medium text-ink-900 hover:text-accent-700">{{ $project->name }}</span>
                <span class="block text-[13px] text-ink-500">{{ $project->code }}</span>
            </a>
        </td>
        <td class="tabular-nums">{{ $project->work_packages_count }}</td>
        <td><x-progress :value="$project->package_progress ?? 0" /></td>
        <td class="whitespace-nowrap">
            @if ($project->pending_updates_count)<x-badge tone="warning">{{ $project->pending_updates_count }} progress</x-badge>@endif
            @if ($project->pending_labour_count)<x-badge tone="warning">{{ $project->pending_labour_count }} labour</x-badge>@endif
            @if (! $project->pending_updates_count && ! $project->pending_labour_count)<span class="text-ink-400">—</span>@endif
        </td>
        <td class="text-right">
            @can('construction:cost-view', $project)
                <x-money :value="$project->total_cost ?? 0" :decimals="false" />
            @else
                <span class="text-ink-400">—</span>
            @endcan
        </td>
    </tr>
@empty
    <tr><td colspan="5"><x-empty-state icon="stairs" title="No projects found" /></td></tr>
@endforelse
