@forelse ($suppliers as $supplier)
    @php($balance = App\Support\Money::sub((string) ($supplier->charges ?? '0'), (string) ($supplier->payments ?? '0')))
    <tr>
        <td><a href="{{ route('suppliers.show', $supplier) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $supplier->name }}</a><p class="text-[13px] text-ink-500">{{ $supplier->supplier_code }}</p></td>
        <td class="text-ink-700">{{ $supplier->contact_person ?: '—' }}@if ($supplier->mobile)<span class="block text-[13px] text-ink-500">{{ App\Support\Phone::display($supplier->mobile) }}</span>@endif</td>
        <td class="text-right"><x-money :value="$balance" @class(['font-semibold text-ink-900' => App\Support\Money::isPositive($balance), 'text-success-600' => App\Support\Money::cmp($balance, '0') < 0]) /></td>
        <td><x-status-badge :status="$supplier->status" /></td>
    </tr>
@empty
    <tr><td colspan="4"><x-empty-state icon="briefcase" title="No suppliers" /></td></tr>
@endforelse
