@php
    $prev = fn (string $key, $row) => $previous ? (string) ($previous[$key]->firstWhere(fn ($r) => $r['account']->id === $row['account']->id)['amount'] ?? '0.00') : null;
    $sections = ['income' => ['Income', 'total_income', 'Total Income'], 'cost_of_sales' => ['Cost of Units Sold', 'gross_profit', 'Gross Profit'],
        'expenses' => ['Expenses', 'profit_before_investors', 'Profit Before Investors'], 'investors' => ["Investors' Share of Profit", 'net_profit', 'Net Profit']];
@endphp
@if ($data['income']->isEmpty() && $data['cost_of_sales']->isEmpty() && $data['expenses']->isEmpty() && $data['investors']->isEmpty())
    <x-empty-state icon="bar-chart" title="No income or expenses in this period" />
@else
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Account</th><th class="w-48 text-right">{{ $previous ? 'This Period' : 'Amount' }}</th>@if ($previous)<th class="w-48 text-right">{{ $before['label'] }}</th>@endif</tr></thead>
            <tbody>
                @foreach ($sections as $key => [$heading, $totalKey, $totalLabel])
                    @if ($data[$key]->isNotEmpty() || $key === 'income')
                        <tr><td colspan="{{ $previous ? 3 : 2 }}" class="pt-4 text-xs font-semibold uppercase tracking-wide text-ink-500">{{ $heading }}</td></tr>
                        @foreach ($data[$key] as $row)
                            @include('accounting.reports._line', ['row' => $row, 'from' => $range['from'], 'compare' => $previous ? $prev($key, $row) : null])
                        @endforeach
                    @endif
                    @if ($data[$key]->isNotEmpty() || in_array($key, ['income', 'investors'], true))
                        @include('accounting.reports._total', ['label' => $totalLabel, 'amount' => $data[$totalKey], 'compare' => $previous ? $previous[$totalKey] : null, 'strong' => $key === 'investors'])
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
@endif
