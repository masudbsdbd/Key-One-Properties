@php($canManage = auth()->user()->can('construction:manage', $project))
@if ($packages->isEmpty())
    <x-empty-state icon="layers" title="No work packages yet" />
@else
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Work Package</th><th>Stage</th><th>Responsible</th><th>Plan</th><th class="text-right">Budget</th><th class="w-44">Progress</th><th>Status</th><th class="w-24"></th></tr></thead>
            <tbody>
                @foreach ($packages as $package)
                    <tr>
                        <td><p class="font-medium text-ink-900">{{ $package->name }}</p><p class="text-[13px] text-ink-500">{{ $package->code }}@if ($package->parent) · under {{ $package->parent->code }}@endif</p></td>
                        <td class="text-ink-600">{{ $package->stage?->name ?? '—' }}</td>
                        <td class="text-ink-600">{{ $package->responsible?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap text-ink-600">{{ App\Support\Format::date($package->planned_start_at) }} – {{ App\Support\Format::date($package->planned_end_at) }}</td>
                        <td class="text-right"><x-money :value="$package->budget_amount" :decimals="false" /></td>
                        <td><x-progress :value="$package->progress_percent" /></td>
                        <td><x-status-badge :status="$package->status" /></td>
                        <td class="whitespace-nowrap text-right">
                            @if ($canManage)
                                <button type="button" class="btn btn-ghost btn-icon" data-remote-modal="{{ route('construction.work-packages.edit', [$project, $package]) }}" data-size="max-w-3xl" aria-label="Edit {{ $package->name }}"><x-icon name="edit" size="h-4 w-4" /></button>
                                <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-action="delete" data-url="{{ route('construction.work-packages.destroy', [$project, $package]) }}" data-confirm="Delete {{ $package->name }}?" data-confirm-text="Delete" aria-label="Delete {{ $package->name }}"><x-icon name="trash" size="h-4 w-4" /></button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
