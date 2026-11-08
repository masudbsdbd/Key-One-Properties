@php($canManage = auth()->user()->can('manageBuildings', [App\Models\Flat::class, $project]))
@if ($buildings->isEmpty())
    <x-empty-state icon="building" title="No buildings yet" />
@else
    <table class="data-table">
        <thead><tr><th>Building</th><th>Floors</th><th>Units</th><th>Available</th><th>Status</th><th class="w-32"></th></tr></thead>
        <tbody>
            @foreach ($buildings as $building)
                <tr>
                    <td><p class="font-medium text-ink-900">{{ $building->name }}</p><p class="text-[13px] text-ink-500">{{ $building->code }}</p></td>
                    <td class="tabular-nums">{{ $building->floors_count }}</td>
                    <td class="tabular-nums">{{ $building->flats_count }}</td>
                    <td class="tabular-nums">{{ $building->available_count }}</td>
                    <td><x-badge :tone="$building->status === 'active' ? 'success' : 'neutral'">{{ App\Models\Building::STATUSES[$building->status] ?? $building->status }}</x-badge></td>
                    <td class="whitespace-nowrap text-right">
                        <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('projects.buildings.floors', [$project, $building]) }}" aria-label="Floors of {{ $building->name }}"><x-icon name="stairs" size="h-4 w-4" /></button>
                        @if ($canManage)
                            <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('projects.buildings.edit', [$project, $building]) }}" aria-label="Edit {{ $building->name }}"><x-icon name="edit" size="h-4 w-4" /></button>
                            <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-action="delete" data-url="{{ route('projects.buildings.destroy', [$project, $building]) }}" data-confirm="Delete {{ $building->name }}?" data-confirm-text="Delete" aria-label="Delete {{ $building->name }}"><x-icon name="trash" size="h-4 w-4" /></button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
