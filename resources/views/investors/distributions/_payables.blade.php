@forelse ($rows as $r)
    @php $due = App\Support\Money::sub((string) $r->payable, (string) $r->paid); @endphp
    <tr>
        <td><a href="{{ route('investors.show', $r->investor) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $r->investor->name }}</a><p class="text-[13px] text-ink-500">{{ $r->investor->investor_code }}</p></td>
        <td class="text-ink-700">{{ $r->flat->project->code }} · {{ $r->flat->building->code }}<span class="block font-medium text-ink-900">Unit {{ $r->flat->flat_no }}</span></td>
        <td class="text-right"><x-money :value="$r->payable" /></td>
        <td class="text-right"><x-money :value="$r->paid" muted /></td>
        <td class="text-right font-medium"><x-money :value="$due" /></td>
        <td class="text-right font-semibold"><x-money :value="$r->available" /></td>
        <td class="text-right">
            @can('investor.profit.distribution.create')
                <x-button size="sm" icon="tag" data-remote-modal="{{ route('distributions.create', ['investor' => $r->investor->public_id, 'flat' => $r->flat->public_id]) }}" data-size="max-w-md" :disabled="! App\Support\Money::isPositive($r->available)" data-disabled-reason="Nothing left to distribute">Distribute</x-button>
            @endcan
        </td>
    </tr>
@empty
    <tr><td colspan="7"><x-empty-state icon="bar-chart" title="No approved profit yet" /></td></tr>
@endforelse
