@php
    $canLedger = auth()->user()->can('accounting.ledger.view');
    $balanced = App\Support\Money::cmp($data['debit'], $data['credit']) === 0;
@endphp
@if ($data['rows']->isEmpty())
    <x-empty-state icon="check-square" title="Nothing posted yet" />
@else
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th class="w-28">Code</th><th>Account</th><th class="w-48 text-right">Debit</th><th class="w-48 text-right">Credit</th></tr></thead>
            <tbody>
                @foreach ($data['rows'] as $row)
                    <tr>
                        <td class="tabular-nums text-ink-600">{{ $row['account']->code }}</td>
                        <td>@if ($canLedger)<a href="{{ route('accounting.ledger', array_filter(['account' => $row['account']->public_id, 'period' => 'all', 'project' => $project?->public_id])) }}" class="ref-link font-medium">{{ $row['account']->name }}</a>@else<span class="font-medium text-ink-900">{{ $row['account']->name }}</span>@endif</td>
                        <td class="text-right tabular-nums">@if ((float) $row['debit'])<x-money :value="$row['debit']" />@endif</td>
                        <td class="text-right tabular-nums">@if ((float) $row['credit'])<x-money :value="$row['credit']" />@endif</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-accent-50/60 text-base font-semibold text-ink-900"><td colspan="2">Total</td><td class="text-right tabular-nums"><x-money :value="$data['debit']" /></td><td class="text-right tabular-nums"><x-money :value="$data['credit']" /></td></tr>
            </tfoot>
        </table>
    </div>
    @unless ($balanced || $project)
        <div class="flex items-center justify-between gap-3 border-t border-danger-500/30 bg-danger-50 px-5 py-3 text-sm font-medium text-danger-600"><span>Debits and credits differ</span><span class="tabular-nums">Difference <x-money :value="App\Support\Money::sub($data['debit'], $data['credit'])" /></span></div>
    @endunless
@endif
