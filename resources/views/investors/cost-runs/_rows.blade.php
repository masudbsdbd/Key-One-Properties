@forelse ($runs as $r)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('cost-runs.show', $r) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $r->run_no }}</a><p class="text-[13px] text-ink-500">v{{ $r->calculation_version }} · {{ App\Support\Format::date($r->created_at) }}</p></td>
        <td class="text-ink-700">{{ $r->project->name }}<span class="block text-[13px] text-ink-500">{{ $r->project->code }}</span></td>
        <td class="whitespace-nowrap">{{ App\Support\Format::date($r->cost_cutoff) }}</td>
        <td class="text-right tabular-nums">{{ App\Support\Format::qty($r->total_saleable_area) }}</td>
        <td class="text-right font-medium"><x-money :value="$r->eligible_shared_cost" /></td>
        <td><x-status-badge :status="$r->status" :labels="App\Models\FlatCostAllocationRun::STATUSES" /></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="layers" title="No cost runs" /></td></tr>
@endforelse
