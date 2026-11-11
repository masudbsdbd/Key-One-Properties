@props(['source'])
{{-- Where a document sits in the accounts: its journals, or why it is still waiting to be posted. --}}
@php
    $user = auth()->user();
    $journals = $user?->can('accounting.journal.view') && $source?->exists
        ? App\Models\JournalEntry::query()->posted()->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())->orderBy('id')->get()
        : collect();
    $waiting = $user?->can('accounting.reconciliation.view') && $source?->exists && App\Modules\Accounting\Support\AccountingSettings::enabled()
        ? App\Modules\Accounting\Services\EventPoster::backlog()->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())->orderBy('id')->get()
        : collect();
@endphp
@if ($journals->isNotEmpty() || $waiting->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-center gap-x-5 gap-y-1.5 rounded-card border border-line bg-surface px-5 py-3 text-sm']) }}>
        <span class="font-semibold text-ink-900">In the Accounts</span>
        @foreach ($journals as $journal)
            <span class="whitespace-nowrap text-ink-600">
                <a href="{{ route('accounting.journals.show', $journal) }}" class="ref-link font-medium">{{ $journal->journal_no }}</a>
                · {{ App\Support\Format::date($journal->accounting_date) }} · <x-money :value="$journal->total_amount" />
                @if ($journal->reversal_of_id)<span class="font-medium text-danger-600">· Reversal</span>@endif
            </span>
        @endforeach
        @foreach ($waiting as $event)
            <a href="{{ route('accounting.reconciliation.index') }}" class="font-medium text-warning-600 underline">{{ $event->processing_status === 'failed' ? 'Not posted: '.$event->last_error : 'Waiting to be posted' }}</a>
        @endforeach
    </div>
@endif
