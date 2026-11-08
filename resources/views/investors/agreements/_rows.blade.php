@forelse ($agreements as $a)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('investor-agreements.show', $a) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $a->agreement_no }}</a><p class="text-[13px] text-ink-500">v{{ $a->version }} · {{ App\Support\Format::date($a->effective_from) }} – {{ $a->effective_to ? App\Support\Format::date($a->effective_to) : 'open' }}</p></td>
        <td class="text-ink-700">{{ $a->investor->name }}<span class="block text-[13px] text-ink-500">{{ $a->investor->investor_code }}</span></td>
        <td class="text-ink-700">{{ $a->project->code }} · {{ $a->flat->building->code }}<span class="block font-medium text-ink-900">Unit {{ $a->flat->flat_no }}</span></td>
        <td class="text-right font-semibold tabular-nums">{{ App\Modules\Investors\Controllers\AgreementController::pct($a->profit_share_percent) }}</td>
        <td class="text-right"><x-money :value="$a->committed_capital" /></td>
        <td><x-status-badge :status="$a->status" :labels="App\Models\InvestorFlatAgreement::STATUSES" /></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="file" title="No agreements" /></td></tr>
@endforelse
