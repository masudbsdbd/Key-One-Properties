@if (! $account)
    <x-empty-state icon="book" title="Select an account" />
@else
    @php $canJournal = auth()->user()->can('accounting.journal.view'); @endphp
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Date</th><th>Details</th><th>For</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Balance</th></tr></thead>
            <tbody>
                @if ($data['lines']->currentPage() === 1)
                    <tr class="bg-surface-muted font-medium text-ink-900"><td colspan="5">Balance at the Start</td><td class="text-right tabular-nums"><x-money :value="$data['opening']" /></td></tr>
                @endif
                @forelse ($data['lines'] as $line)
                    @php $party = $line->customer ?? $line->supplier ?? $line->contractor ?? $line->investor; @endphp
                    <tr>
                        <td class="whitespace-nowrap text-ink-700">{{ App\Support\Format::date($line->entry->accounting_date) }}
                            <p class="text-[13px] text-ink-500">@if ($canJournal)<a href="{{ route('accounting.journals.show', $line->entry) }}" class="ref-link">{{ $line->entry->journal_no }}</a>@else{{ $line->entry->journal_no }}@endif</p></td>
                        <td class="max-w-md text-ink-700"><p class="truncate" data-tooltip="{{ $line->entry->narration }}">{{ $line->description ?: $line->entry->narration }}</p>
                            @if ($line->entry->source && ! $line->entry->source instanceof App\Models\MoneyAccount)<p class="text-[13px] text-ink-500"><x-ref :to="$line->entry->source">{{ App\Support\ActivityPresenter::type($line->entry->source_type) }}</x-ref></p>@endif</td>
                        <td class="whitespace-nowrap text-ink-700">
                            @if ($party)<x-ref :to="$party">{{ $party->name }}</x-ref>@endif
                            <p class="text-[13px] text-ink-500">@if ($line->project)<x-ref :to="$line->project">{{ $line->project->code }}</x-ref>@endif @if ($line->flat)<x-ref :to="$line->flat">Unit {{ $line->flat->flat_no }}</x-ref>@endif</p></td>
                        <td class="text-right tabular-nums">@if ((float) $line->debit)<x-money :value="$line->debit" />@endif</td>
                        <td class="text-right tabular-nums">@if ((float) $line->credit)<x-money :value="$line->credit" />@endif</td>
                        <td @class(['text-right tabular-nums font-medium', 'text-danger-600' => App\Support\Money::cmp($line->balance, 0) < 0])><x-money :value="$line->balance" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-sm text-ink-500">No entries in this period</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bg-accent-50/60 font-semibold text-ink-900"><td colspan="3">Balance at the End</td><td class="text-right tabular-nums"><x-money :value="$data['debit']" /></td><td class="text-right tabular-nums"><x-money :value="$data['credit']" /></td><td class="text-right tabular-nums"><x-money :value="$data['closing']" /></td></tr>
            </tfoot>
        </table>
    </div>
    @if ($data['lines']->hasPages())
        <div class="flex items-center justify-between gap-3 border-t border-line px-5 py-3 text-sm text-ink-600">
            <span>{{ $data['lines']->firstItem() }}–{{ $data['lines']->lastItem() }} of {{ $data['lines']->total() }}</span>
            <span class="flex gap-2">
                @if ($data['lines']->onFirstPage())<x-button size="sm" variant="secondary" disabled data-disabled-reason="First page">Previous</x-button>@else<x-button size="sm" variant="secondary" :href="$data['lines']->appends(request()->except(['partial', 'page']))->previousPageUrl()" data-report-link>Previous</x-button>@endif
                @if ($data['lines']->hasMorePages())<x-button size="sm" variant="secondary" :href="$data['lines']->appends(request()->except(['partial', 'page']))->nextPageUrl()" data-report-link>Next</x-button>@else<x-button size="sm" variant="secondary" disabled data-disabled-reason="Last page">Next</x-button>@endif
            </span>
        </div>
    @endif
@endif
