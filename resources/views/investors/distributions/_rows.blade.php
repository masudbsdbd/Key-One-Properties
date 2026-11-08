@forelse ($distributions as $d)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('distributions.show', $d) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $d->distribution_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($d->created_at) }}</p></td>
        <td class="text-ink-700">{{ $d->investor->name }}<span class="block text-[13px] text-ink-500">{{ $d->investor->investor_code }}</span></td>
        <td class="text-ink-700">{{ $d->flat->project->code }} · Unit {{ $d->flat->flat_no }}</td>
        <td class="text-right font-medium"><x-money :value="$d->amount" /></td>
        <td><x-status-badge :status="$d->status" :labels="App\Models\InvestorProfitDistribution::STATUSES" /></td>
    </tr>
@empty
    <tr><td colspan="5"><x-empty-state icon="check-square" title="No distributions" /></td></tr>
@endforelse
