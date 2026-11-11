@php
    $labels = ['operating' => 'Day-to-Day Business', 'investing' => 'Assets Bought and Sold', 'financing' => 'Owners, Investors and Loans'];
    $gap = App\Support\Money::sub($data['closing'], $data['book_closing']);
    $canLedger = auth()->user()->can('accounting.ledger.view');
@endphp
<div class="overflow-x-auto">
    <table class="data-table">
        <thead><tr><th>Money In (+) and Out (−)</th><th class="w-48 text-right">Amount</th></tr></thead>
        <tbody>
            @include('accounting.reports._total', ['label' => 'Cash & Bank at the Start', 'amount' => $data['opening']])
            @foreach ($labels as $key => $label)
                @if ($data['sections'][$key]->isNotEmpty())
                    <tr><td colspan="2" class="pt-4 text-xs font-semibold uppercase tracking-wide text-ink-500">{{ $label }}</td></tr>
                    @foreach ($data['sections'][$key] as $row)
                        <tr>
                            <td class="pl-9"><p class="text-ink-900">{{ $row['label'] }}</p>
                                <p class="text-[13px] text-ink-500">@foreach ($row['accounts'] as $a)@if ($canLedger)<a href="{{ route('accounting.ledger', array_filter(['account' => $a->public_id, 'period' => 'custom', 'from' => $range['from'], 'to' => $range['to']])) }}" class="ref-link mr-2">{{ $a->name }}</a>@else<span class="mr-2">{{ $a->name }}</span>@endif @endforeach</p></td>
                            <td @class(['text-right tabular-nums align-top', 'text-danger-600' => App\Support\Money::cmp($row['amount'], 0) < 0])><x-money :value="$row['amount']" /></td>
                        </tr>
                    @endforeach
                    @include('accounting.reports._total', ['label' => 'Net from '.$label, 'amount' => $data['totals'][$key]])
                @endif
            @endforeach
            @include('accounting.reports._total', ['label' => 'Change in Cash & Bank', 'amount' => $data['change']])
            @include('accounting.reports._total', ['label' => 'Cash & Bank at the End', 'amount' => $data['closing'], 'strong' => true])
        </tbody>
    </table>
</div>
@if (App\Support\Money::cmp($gap, 0) !== 0)
    <div class="flex items-center justify-between gap-3 border-t border-danger-500/30 bg-danger-50 px-5 py-3 text-sm font-medium text-danger-600">
        <span>Cash & Bank accounts show <x-money :value="$data['book_closing']" /></span><span class="tabular-nums">Difference <x-money :value="$gap" /></span>
    </div>
@endif
