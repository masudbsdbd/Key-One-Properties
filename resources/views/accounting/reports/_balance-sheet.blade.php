@php $balanced = App\Support\Money::cmp($data['difference'], 0) === 0; @endphp
<div class="grid gap-0 xl:grid-cols-2 xl:divide-x xl:divide-line">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Assets</th><th class="w-44 text-right">Amount</th></tr></thead>
            <tbody>
                @foreach ($data['assets'] as $row)@include('accounting.reports._line', ['row' => $row])@endforeach
                @include('accounting.reports._total', ['label' => 'Total Assets', 'amount' => $data['total_assets'], 'strong' => true])
            </tbody>
        </table>
    </div>
    <div class="overflow-x-auto border-t border-line xl:border-t-0">
        <table class="data-table">
            <thead><tr><th>Liabilities</th><th class="w-44 text-right">Amount</th></tr></thead>
            <tbody>
                @foreach ($data['liabilities'] as $row)@include('accounting.reports._line', ['row' => $row])@endforeach
                @include('accounting.reports._total', ['label' => 'Total Liabilities', 'amount' => $data['total_liabilities']])
                <tr><td colspan="2" class="pt-4 text-xs font-semibold uppercase tracking-wide text-ink-500">Equity</td></tr>
                @foreach ($data['equity'] as $row)@include('accounting.reports._line', ['row' => $row])@endforeach
                <tr><td class="pl-9 text-ink-700">Profit Not Yet Closed</td><td @class(['text-right tabular-nums', 'text-danger-600' => App\Support\Money::cmp($data['earnings'], 0) < 0])><x-money :value="$data['earnings']" /></td></tr>
                @include('accounting.reports._total', ['label' => 'Total Equity', 'amount' => $data['total_equity']])
                @include('accounting.reports._total', ['label' => 'Liabilities + Equity', 'amount' => $data['total_liabilities_equity'], 'strong' => true])
            </tbody>
        </table>
    </div>
</div>
<div @class(['flex items-center justify-between gap-3 border-t px-5 py-3 text-sm font-medium', 'border-line text-success-600' => $balanced, 'border-danger-500/30 bg-danger-50 text-danger-600' => ! $balanced])>
    <span>{{ $balanced ? 'Assets equal liabilities plus equity' : 'Assets and liabilities plus equity differ' }}</span>
    @unless ($balanced)<span class="tabular-nums">Difference <x-money :value="$data['difference']" /></span>@endunless
</div>
