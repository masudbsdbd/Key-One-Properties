@forelse ($agreements as $a)
    <tr>
        <td><a href="{{ route('investor.investments.show', $a->public_id) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $a->project->name }} · Unit {{ $a->flat->flat_no }}</a><span class="block text-[13px] text-ink-500">{{ $a->agreement_no }}</span></td>
        <td class="text-right font-semibold tabular-nums">{{ App\Modules\Investors\Controllers\AgreementController::pct($a->profit_share_percent) }}</td>
        <td class="text-right"><x-money :value="$a->committed_capital" /></td>
        <td class="whitespace-nowrap text-ink-700">{{ App\Support\Format::date($a->effective_from) }}@if ($a->effective_to) – {{ App\Support\Format::date($a->effective_to) }}@endif</td>
        <td><x-status-badge :status="$a->status" :labels="App\Models\InvestorFlatAgreement::STATUSES" /></td>
        <td class="text-right"><a href="{{ route('investor.investments.show', $a->public_id) }}" class="btn btn-secondary btn-sm">Details<x-icon name="chevron-right" size="h-4 w-4" /></a></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="briefcase" title="No investments" /></td></tr>
@endforelse
