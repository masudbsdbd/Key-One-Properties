@forelse ($policies as $p)
    <tr>
        <td><a href="{{ route('customers.statement', $p->customer) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $p->customer->full_name }}</a><span class="block text-[13px] text-ink-500">{{ $p->customer->customer_code }}</span></td>
        <td class="text-ink-700">{{ $p->sale?->sale_no ?? 'All sales' }}</td>
        <td class="text-ink-700">{{ App\Models\CustomerLateFeePolicy::TYPES[$p->fee_type] }}@if ($p->fee_type !== 'none')<span class="block font-medium text-ink-900">{{ $p->fee_type === 'percent' ? rtrim(rtrim($p->fee_value, '0'), '.').'%' : App\Support\Format::money($p->fee_value) }}</span>@endif</td>
        <td class="tabular-nums text-ink-700">{{ $p->grace_days }} days</td>
        <td class="text-ink-700">{{ App\Models\CustomerLateFeePolicy::FREQUENCIES[$p->frequency] }}</td>
        <td class="whitespace-nowrap text-ink-700">{{ App\Support\Format::date($p->effective_from) }}<span class="block text-[13px] text-ink-500">{{ $p->effective_to ? 'until '.App\Support\Format::date($p->effective_to) : 'open' }}</span></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="alert" title="No late-fee policies" /></td></tr>
@endforelse
