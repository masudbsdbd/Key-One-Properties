<x-card :padded="false">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>What</th><th class="text-right">In the Records</th><th class="text-right">In the Ledger</th><th class="text-right">Difference</th></tr></thead>
            <tbody>
                @foreach ($checks as $check)
                    @php $ok = App\Support\Money::cmp($check['difference'], 0) === 0; @endphp
                    <tr>
                        <td><p class="font-medium text-ink-900">{{ $check['label'] }}</p>
                            <p class="text-[13px] text-ink-500">@foreach ($check['accounts'] as $a)@if ($canLedger)<a href="{{ route('accounting.ledger', ['account' => $a->public_id, 'period' => 'all']) }}" class="ref-link mr-2">{{ $a->name }}</a>@else<span class="mr-2">{{ $a->name }}</span>@endif @endforeach</p></td>
                        <td class="text-right tabular-nums"><x-money :value="$check['records']" /></td>
                        <td class="text-right tabular-nums"><x-money :value="$check['ledger']" /></td>
                        <td class="text-right">@if ($ok)<x-badge tone="success">Matches</x-badge>@else<span class="font-semibold tabular-nums text-danger-600"><x-money :value="$check['difference']" /></span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-card>
