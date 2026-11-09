@forelse ($agreements as $a)
    <tr>
        <td><a href="{{ route('investor.investments.show', $a->public_id) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $a->project->name }}</a><span class="block text-[13px] text-ink-500">{{ $a->flat->building->code }} · Unit {{ $a->flat->flat_no }}</span></td>
        <td class="whitespace-nowrap text-ink-700">{{ $a->agreement_no }}<span class="block text-[13px] text-ink-500">v{{ $a->version }}</span></td>
        <td class="text-right font-semibold tabular-nums">{{ App\Modules\Investors\Controllers\AgreementController::pct($a->profit_share_percent) }}</td>
        <td class="text-right"><x-money :value="$a->committed_capital" /></td>
        <td class="whitespace-nowrap text-ink-700">{{ App\Support\Format::date($a->effective_from) }} – {{ $a->effective_to ? App\Support\Format::date($a->effective_to) : 'open' }}</td>
        <td><x-status-badge :status="$a->status" :labels="App\Models\InvestorFlatAgreement::STATUSES" /></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="briefcase" title="No investments" /></td></tr>
@endforelse
