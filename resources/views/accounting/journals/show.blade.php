@php
    $s = $journal->status;
    $user = auth()->user();
    $party = fn ($l) => collect([
        $l->project ? [$l->project, $l->project->code] : null,
        $l->flat ? [$l->flat, 'Unit '.$l->flat->flat_no] : null,
        $l->customer ? [$l->customer, $l->customer->name] : null,
        $l->supplier ? [$l->supplier, $l->supplier->name] : null,
        $l->contractor ? [$l->contractor, $l->contractor->name] : null,
        $l->investor ? [$l->investor, $l->investor->name] : null,
    ])->filter();
@endphp
<x-layouts.app :title="$journal->journal_no">
    <x-page-header :title="$journal->journal_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Journals' => route('accounting.journals.index'), $journal->journal_no => null]">
        <x-slot:actions>
            @if ($editable && $user->can('accounting.journal.create'))
                <x-button variant="secondary" icon="edit" :href="route('accounting.journals.edit', $journal)">Edit</x-button>
                <x-button icon="arrow-right" data-action="post" data-url="{{ route('accounting.journals.decide', [$journal, 'submit']) }}" data-reload>Submit for Approval</x-button>
            @endif
            @if ($s === 'submitted' && $user->can('accounting.journal.approve'))
                <x-button variant="ghost" data-remote-modal="{{ route('accounting.journals.reason', [$journal, 'reject']) }}" data-size="max-w-md">Reject</x-button>
                @if ($user->can('accounting.journal.post'))
                    <x-button icon="check" data-action="post" data-url="{{ route('accounting.journals.decide', [$journal, 'approve-post']) }}" data-reload data-confirm="Approve and post {{ $journal->journal_no }}?" data-confirm-message="A posted journal cannot be edited." data-confirm-text="Approve & Post" data-tone="primary">Approve &amp; Post</x-button>
                @else
                    <x-button icon="check" data-action="post" data-url="{{ route('accounting.journals.decide', [$journal, 'approve']) }}" data-reload>Approve</x-button>
                @endif
            @endif
            @if ($s === 'approved' && $user->can('accounting.journal.post'))
                <x-button icon="check" data-action="post" data-url="{{ route('accounting.journals.decide', [$journal, 'post']) }}" data-reload data-confirm="Post {{ $journal->journal_no }}?" data-confirm-message="A posted journal cannot be edited." data-confirm-text="Post" data-tone="primary">Post</x-button>
            @endif
            @if ($s === 'posted' && ! $journal->isReversed() && ! $journal->reversal_of_id && $user->can('accounting.journal.reverse'))
                <x-button variant="ghost" icon="restore" data-remote-modal="{{ route('accounting.journals.reason', [$journal, 'reverse']) }}" data-size="max-w-md">Reverse</x-button>
            @endif
            @if ($editable && $user->can('accounting.journal.create'))
                <x-button variant="ghost" icon="trash" data-action="delete" data-url="{{ route('accounting.journals.destroy', $journal) }}" data-confirm="Delete {{ $journal->journal_no }}?" data-confirm-text="Delete">Delete</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($journal->status_reason)
        <div class="mb-6 rounded-card border border-danger-500/30 bg-danger-50 px-5 py-3 text-sm text-danger-600">{{ $journal->status_reason }}</div>
    @endif
    @if ($journal->reversedBy)
        <div class="mb-6 rounded-card border border-danger-500/30 bg-danger-50 px-5 py-3 text-sm text-danger-600">Reversed by <a href="{{ route('accounting.journals.show', $journal->reversedBy) }}" class="font-semibold underline">{{ $journal->reversedBy->journal_no }}</a> on {{ App\Support\Format::date($journal->reversedBy->accounting_date) }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card class="min-w-0 xl:col-span-2">
            <p class="text-[15px] font-medium text-ink-900">{{ $journal->narration }}</p>
            <div class="mt-5 grid gap-5 sm:grid-cols-3">
                <div><p class="detail-label">Date</p><p class="detail-value">{{ App\Support\Format::date($journal->accounting_date) }}@if ($journal->period) · {{ $journal->period->name }}@endif</p></div>
                <div><p class="detail-label">Type</p><p class="detail-value">{{ App\Models\JournalEntry::TYPES[$journal->journal_type] ?? $journal->journal_type }}</p></div>
                <div><p class="detail-label">Reference</p><p class="detail-value">{{ $journal->reference ?: '—' }}</p></div>
                @if ($journal->source)
                    <div><p class="detail-label">Source</p><p class="detail-value"><x-ref :to="$journal->source">{{ App\Support\ActivityPresenter::type($journal->source_type) }}</x-ref></p></div>
                @endif
                @if ($journal->reversalOf)
                    <div><p class="detail-label">Reverses</p><p class="detail-value"><a href="{{ route('accounting.journals.show', $journal->reversalOf) }}" class="ref-link">{{ $journal->reversalOf->journal_no }}</a></p></div>
                @endif
                <div><p class="detail-label">Created By</p><p class="detail-value">{{ $journal->creator?->name ?? 'System' }}</p></div>
                @if ($journal->approver)<div><p class="detail-label">{{ $s === 'rejected' ? 'Rejected By' : 'Approved By' }}</p><p class="detail-value">{{ $journal->approver->name }} · {{ App\Support\Format::datetime($journal->approved_at) }}</p></div>@endif
                @if ($journal->posted_at)<div><p class="detail-label">Posted</p><p class="detail-value">{{ $journal->poster?->name ?? 'System' }} · {{ App\Support\Format::datetime($journal->posted_at) }}</p></div>@endif
            </div>
        </x-card>
        <x-card title="Amount">
            <p class="text-[28px] font-semibold text-ink-900"><x-money :value="$journal->total_amount" /></p>
            <div class="mt-3"><x-status-badge :status="$s" :labels="App\Models\JournalEntry::STATUSES" /></div>
        </x-card>
    </div>

    <x-card title="Lines" :padded="false" class="mt-6">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Account</th><th>For</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead>
                <tbody>
                    @foreach ($journal->lines as $line)
                        <tr>
                            <td><p class="font-medium text-ink-900">{{ $line->account->name }}</p><p class="text-[13px] text-ink-500">{{ $line->account->code }}@if ($line->description) · {{ $line->description }}@endif</p></td>
                            <td class="text-ink-700">
                                @forelse ($party($line) as [$record, $text])<x-ref :to="$record" class="mr-2">{{ $text }}</x-ref>@empty — @endforelse
                                @if ($line->moneyAccount)<span class="text-ink-500">{{ $line->moneyAccount->label() }}</span>@endif
                            </td>
                            <td class="text-right tabular-nums">@if ((float) $line->debit)<x-money :value="$line->debit" />@endif</td>
                            <td class="text-right tabular-nums">@if ((float) $line->credit)<x-money :value="$line->credit" />@endif</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="font-semibold text-ink-900"><td colspan="2">Total</td><td class="text-right"><x-money :value="$journal->lines->sum('debit')" /></td><td class="text-right"><x-money :value="$journal->lines->sum('credit')" /></td></tr>
                </tfoot>
            </table>
        </div>
    </x-card>

    <x-card title="History" :padded="false" class="mt-6">@include('projects.partials._activity', ['entries' => $activity])</x-card>
</x-layouts.app>
