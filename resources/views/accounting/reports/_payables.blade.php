@php $canLedger = auth()->user()->can('accounting.ledger.view'); @endphp
<div class="overflow-x-auto">
    <table class="data-table">
        <thead><tr><th>Owed To</th><th>Made Up Of</th><th class="w-48 text-right">We Owe</th></tr></thead>
        <tbody>
            @foreach ($sections as $key => [$label, $rows, $subtotal])
                @if ($rows->isNotEmpty())
                    <tr><td colspan="3" class="pt-4 text-xs font-semibold uppercase tracking-wide text-ink-500">{{ $label }}</td></tr>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="pl-9">
                                @if ($key === 'other')
                                    @if ($canLedger)<a href="{{ route('accounting.ledger', ['account' => $row['account']->public_id, 'period' => 'all']) }}" class="ref-link font-medium">{{ $row['name'] }}</a>@else<span class="font-medium text-ink-900">{{ $row['name'] }}</span>@endif
                                @elseif ($row['party'])<x-ref :to="$row['party']" class="font-medium">{{ $row['name'] }}</x-ref>
                                @else<span class="text-ink-600">{{ $row['name'] }}</span>@endif
                            </td>
                            <td class="text-[13px] text-ink-500">
                                @if ($key === 'contractors')
                                    Bills <x-money :value="$row['payable']" />@if ((float) $row['retention']) · Retention held <x-money :value="$row['retention']" />@endif @if ((float) $row['advance']) · Less advance <x-money :value="$row['advance']" />@endif
                                @elseif ($key === 'investors')
                                    Capital <x-money :value="$row['capital']" />@if ((float) $row['profit']) · Profit <x-money :value="$row['profit']" />@endif
                                @endif
                            </td>
                            <td @class(['text-right tabular-nums', 'text-danger-600' => App\Support\Money::cmp($row['amount'], 0) < 0])><x-money :value="$row['amount']" /></td>
                        </tr>
                    @endforeach
                    <tr class="bg-surface-muted font-semibold text-ink-900"><td colspan="2">Total {{ $label }}</td><td class="text-right tabular-nums"><x-money :value="$subtotal" /></td></tr>
                @endif
                @if ($key === 'other')
                    <tr class="bg-accent-50/60 font-semibold text-ink-900"><td colspan="2">To Pay Suppliers, Contractors and Others</td><td class="text-right tabular-nums"><x-money :value="$trade" /></td></tr>
                @endif
            @endforeach
        </tbody>
        <tfoot><tr class="bg-accent-50/60 text-base font-semibold text-ink-900"><td colspan="2">Total with Investors</td><td class="text-right tabular-nums"><x-money :value="$total" /></td></tr></tfoot>
    </table>
</div>
