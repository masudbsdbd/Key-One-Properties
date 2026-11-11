<x-badge :tone="['in_hand' => 'warning', 'deposited' => 'info', 'cleared' => 'success', 'bounced' => 'danger'][$cheque->status]">{{ App\Models\Cheque::STATUSES[$cheque->status] }}</x-badge>
<p class="mt-1 text-[13px] text-ink-500">
    @if ($cheque->status === 'deposited'){{ $cheque->moneyAccount?->name }} · {{ App\Support\Format::date($cheque->deposited_on) }}
    @elseif ($cheque->status === 'cleared'){{ $cheque->moneyAccount?->name }} · {{ App\Support\Format::date($cheque->settled_on) }}
    @elseif ($cheque->status === 'bounced'){{ App\Support\Format::date($cheque->settled_on) }}@if ($cheque->status_note) · {{ $cheque->status_note }}@endif
    @endif
</p>
