@forelse ($investors as $i)
    <tr>
        <td><a href="{{ route('investors.show', $i) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $i->name }}</a><p class="text-[13px] text-ink-500">{{ $i->investor_code }}</p></td>
        <td class="text-ink-700">{{ App\Support\Phone::display($i->mobile) ?: '—' }}<span class="block text-[13px] text-ink-500">{{ $i->email }}</span></td>
        <td class="text-right tabular-nums">{{ $i->active_agreements }}</td>
        <td class="text-right font-medium"><x-money :value="$i->capital ?? '0'" /></td>
        <td><x-status-badge :status="$i->status" :labels="App\Models\Investor::STATUSES" /></td>
    </tr>
@empty
    <tr><td colspan="5"><x-empty-state icon="users" title="No investors" /></td></tr>
@endforelse
