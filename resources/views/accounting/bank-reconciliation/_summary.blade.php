@php $zero = App\Support\Money::cmp($summary['difference'], 0) === 0; @endphp
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat-card label="On the Statement" :value="App\Support\Format::money($summary['statement'])" icon="bank" tone="info" />
    <x-stat-card label="Ticked Off" :value="App\Support\Format::money($summary['ticked'])" icon="check-square" tone="accent" />
    <x-stat-card label="Difference" :value="App\Support\Format::money($summary['difference'])" icon="alert" :tone="$zero ? 'success' : 'danger'" />
    <x-stat-card :label="'Not Ticked · '.$summary['open_count']" :value="App\Support\Format::money($summary['open_amount'])" icon="list" tone="warning" />
</div>
@if ($reconciliation->status === 'draft')
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-ink-600">In the books on {{ App\Support\Format::date($reconciliation->statement_date) }}: <span class="font-semibold tabular-nums text-ink-900"><x-money :value="$summary['book']" /></span></p>
        <div class="flex gap-2">
            <x-button variant="ghost" icon="trash" data-action="delete" data-url="{{ route('accounting.bank-reconciliation.destroy', $reconciliation) }}" data-confirm="Remove this reconciliation?" data-confirm-text="Remove">Remove</x-button>
            <x-button icon="check" data-action="post" data-url="{{ route('accounting.bank-reconciliation.complete', $reconciliation) }}" :disabled="! $zero" :data-disabled-reason="$zero ? null : 'The difference must be zero'">Complete</x-button>
        </div>
    </div>
@endif
