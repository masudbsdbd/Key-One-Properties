{{-- One account line of a statement; the name opens the general ledger for the same period. --}}
@php
    $canLedger ??= auth()->user()->can('accounting.ledger.view');
    $query = array_filter(['account' => $row['account']->public_id, 'period' => 'custom', 'from' => $from ?? null, 'to' => $range['to'], 'project' => ($project ?? null)?->public_id]);
@endphp
<tr>
    <td class="pl-9 text-ink-700">
        @if ($canLedger)<a href="{{ route('accounting.ledger', $query) }}" class="ref-link">{{ $row['account']->name }}</a>@else{{ $row['account']->name }}@endif
        <span class="ml-1 text-[13px] text-ink-400">{{ $row['account']->code }}</span>
    </td>
    <td @class(['text-right tabular-nums', 'text-danger-600' => App\Support\Money::cmp($row['amount'], 0) < 0])><x-money :value="$row['amount']" /></td>
    @isset($compare)<td class="text-right tabular-nums text-ink-500"><x-money :value="$compare" /></td>@endisset
</tr>
