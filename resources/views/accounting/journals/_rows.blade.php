@forelse ($journals as $j)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('accounting.journals.show', $j) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $j->journal_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($j->accounting_date) }}</p></td>
        <td class="whitespace-nowrap text-ink-700">{{ App\Models\JournalEntry::TYPES[$j->journal_type] ?? $j->journal_type }}</td>
        <td class="max-w-md text-ink-700"><p class="truncate" data-tooltip="{{ $j->narration }}">{{ $j->narration }}</p>
            @if ($j->reversalOf)<p class="text-[13px] text-ink-500">Reverses <a href="{{ route('accounting.journals.show', $j->reversalOf) }}" class="ref-link">{{ $j->reversalOf->journal_no }}</a></p>
            @elseif ($j->reference)<p class="text-[13px] text-ink-500">Ref {{ $j->reference }}</p>@endif
        </td>
        <td class="text-right font-medium"><x-money :value="$j->total_amount" /></td>
        <td class="whitespace-nowrap">
            <x-status-badge :status="$j->status" :labels="App\Models\JournalEntry::STATUSES" />
            @if ($j->reversedBy)<p class="mt-1 text-[13px] text-danger-600">Reversed by <a href="{{ route('accounting.journals.show', $j->reversedBy) }}" class="underline">{{ $j->reversedBy->journal_no }}</a></p>@endif
        </td>
    </tr>
@empty
    <tr><td colspan="5"><x-empty-state icon="file" title="No journals" /></td></tr>
@endforelse
