@forelse ($lines as $line)
    @php
        $entry = $line->entry;
        $party = $line->customer ?? $line->supplier ?? $line->contractor ?? $line->investor;
        $txn = $entry->source instanceof App\Models\MoneyTransaction ? $entry->source : null;
    @endphp
    <tr>
        <td class="whitespace-nowrap text-ink-700">{{ App\Support\Format::date($entry->accounting_date) }}
            <p class="text-[13px] text-ink-500">@if ($canJournal)<a href="{{ route('accounting.journals.show', $entry) }}" class="ref-link">{{ $entry->journal_no }}</a>@else{{ $entry->journal_no }}@endif</p>
        </td>
        @unless ($single)<td class="whitespace-nowrap"><a href="{{ route('accounting.cash-bank.show', $line->moneyAccount) }}" class="ref-link font-medium">{{ $line->moneyAccount->name }}</a></td>@endunless
        <td class="max-w-md text-ink-700"><p class="truncate" data-tooltip="{{ $entry->narration }}">{{ $line->description ?: $entry->narration }}</p>
            <p class="text-[13px] text-ink-500">
                @if ($party)<x-ref :to="$party">{{ $party->name }}</x-ref>@endif
                @if ($entry->source && ! $txn && ! $entry->source instanceof App\Models\MoneyAccount)<x-ref :to="$entry->source" @class(['ml-2' => $party])>{{ App\Support\ActivityPresenter::type($entry->source_type) }}</x-ref>@endif
                @if ($entry->reference) <span @class(['ml-2' => $party])>Ref {{ $entry->reference }}</span>@endif
                @if ($txn && $txn->status === 'reversed')<span class="font-medium text-danger-600">Cancelled</span>@endif
                @if ($entry->reversal_of_id)<span class="font-medium text-danger-600">Reversal</span>@endif
            </p>
        </td>
        <td class="text-right font-medium tabular-nums text-success-600">@if ((float) $line->debit)<x-money :value="$line->debit" />@endif</td>
        <td class="text-right font-medium tabular-nums text-ink-900">@if ((float) $line->credit)<x-money :value="$line->credit" />@endif</td>
        @if ($single)<td @class(['text-right tabular-nums', 'text-danger-600' => App\Support\Money::cmp($line->balance ?? 0, 0) < 0])>@isset($line->balance)<x-money :value="$line->balance" />@endisset</td>@endif
        <td class="text-right">
            @if ($canManage && $txn && $txn->status === 'posted' && ! $entry->reversal_of_id)
                <x-button size="sm" variant="ghost" data-remote-modal="{{ route('accounting.cash-bank.cancel', $txn) }}" data-size="max-w-md">Cancel</x-button>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="wallet" title="No money in or out" /></td></tr>
@endforelse
