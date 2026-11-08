@forelse ($contractors as $contractor)
    @php($balance = App\Support\Money::sub((string) ($contractor->charges ?? '0'), (string) ($contractor->payments ?? '0')))
    <tr>
        <td><a href="{{ route('contractors.show', $contractor) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $contractor->name }}</a><p class="text-[13px] text-ink-500">{{ $contractor->contractor_code }}</p></td>
        <td class="text-ink-700">{{ $contractor->trade ?: '—' }}</td>
        <td class="text-ink-700">{{ $contractor->contact_person ?: '—' }}@if ($contractor->mobile)<span class="block text-[13px] text-ink-500">{{ App\Support\Phone::display($contractor->mobile) }}</span>@endif</td>
        <td class="text-right tabular-nums">{{ $contractor->active_orders }}</td>
        <td class="text-right"><x-money :value="$balance" @class(['font-semibold text-ink-900' => App\Support\Money::isPositive($balance), 'text-success-600' => App\Support\Money::cmp($balance, '0') < 0]) /></td>
        <td><x-status-badge :status="$contractor->status" /></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="user" title="No contractors" /></td></tr>
@endforelse
