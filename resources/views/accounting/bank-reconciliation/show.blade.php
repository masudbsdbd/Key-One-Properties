@php
    $account = $reconciliation->moneyAccount;
    $draft = $reconciliation->status === 'draft';
    $title = $account->name.' · '.App\Support\Format::date($reconciliation->statement_date);
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :breadcrumbs="['Dashboard' => route('dashboard'), 'Cash & Bank' => route('accounting.cash-bank.index'), $account->name => route('accounting.cash-bank.show', $account), 'Reconcile' => route('accounting.bank-reconciliation.index', $account), App\Support\Format::date($reconciliation->statement_date) => null]" />

    <div data-recon-summary>@include('accounting.bank-reconciliation._summary')</div>

    <x-card :title="$draft ? 'Entries up to the Statement Date' : 'Entries Ticked Off'" :padded="false" class="mt-6" data-recon data-url="{{ route('accounting.bank-reconciliation.tick', $reconciliation) }}">
        @if ($draft && $lines->isNotEmpty())
            <x-slot:actions><x-button size="sm" variant="secondary" data-recon-all>Tick All</x-button></x-slot:actions>
        @endif
        @if ($lines->isEmpty())
            <x-empty-state icon="check-square" title="No entries to tick" />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr>@if ($draft)<th class="w-12"></th>@endif<th>Date</th><th>Details</th><th class="text-right">In</th><th class="text-right">Out</th></tr></thead>
                    <tbody>
                        @foreach ($lines as $line)
                            @php $token = $line->entry->public_id.'-'.$line->line_no; @endphp
                            <tr>
                                @if ($draft)<td><input type="checkbox" class="form-check" value="{{ $token }}" @checked($line->bank_reconciliation_id === $reconciliation->id) data-recon-line aria-label="Tick {{ $line->entry->journal_no }}"></td>@endif
                                <td class="whitespace-nowrap text-ink-700">{{ App\Support\Format::date($line->entry->accounting_date) }}<p class="text-[13px] text-ink-500">{{ $line->entry->journal_no }}</p></td>
                                <td class="max-w-lg text-ink-700"><p class="truncate" data-tooltip="{{ $line->entry->narration }}">{{ $line->description ?: $line->entry->narration }}</p>@if ($line->entry->reference)<p class="text-[13px] text-ink-500">Ref {{ $line->entry->reference }}</p>@endif</td>
                                <td class="text-right font-medium tabular-nums text-success-600">@if ((float) $line->debit)<x-money :value="$line->debit" />@endif</td>
                                <td class="text-right font-medium tabular-nums text-ink-900">@if ((float) $line->credit)<x-money :value="$line->credit" />@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.app>
