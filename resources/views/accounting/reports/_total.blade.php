<tr @class(['font-semibold text-ink-900', 'bg-accent-50/60 text-base' => $strong ?? false, 'bg-surface-muted' => ! ($strong ?? false)])>
    <td>{{ $label }}</td>
    <td @class(['text-right tabular-nums', 'text-danger-600' => App\Support\Money::cmp($amount, 0) < 0])><x-money :value="$amount" /></td>
    @isset($compare)<td class="text-right tabular-nums text-ink-600"><x-money :value="$compare" /></td>@endisset
</tr>
