@if ($rows->isEmpty())
    <x-empty-state icon="users" title="No customer owes anything" />
@else
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Customer</th><th class="w-48 text-right">Owes Us</th><th class="w-48 text-right">Advance We Hold</th><th class="w-48 text-right">Net</th></tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>@if ($row['party'])<x-ref :to="$row['party']" class="font-medium">{{ $row['name'] }}</x-ref><span class="ml-1 text-[13px] text-ink-400">{{ $row['code'] }}</span>@else<span class="text-ink-600">{{ $row['name'] }}</span>@endif</td>
                        <td class="text-right tabular-nums"><x-money :value="$row['owes']" /></td>
                        <td class="text-right tabular-nums text-ink-600"><x-money :value="$row['advance']" /></td>
                        <td @class(['text-right tabular-nums font-semibold', 'text-danger-600' => App\Support\Money::cmp($row['net'], 0) < 0])><x-money :value="$row['net']" /></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr class="bg-accent-50/60 text-base font-semibold text-ink-900"><td>Total</td><td class="text-right tabular-nums"><x-money :value="$totals['owes']" /></td><td class="text-right tabular-nums"><x-money :value="$totals['advance']" /></td><td class="text-right tabular-nums"><x-money :value="$totals['net']" /></td></tr></tfoot>
        </table>
    </div>
@endif
