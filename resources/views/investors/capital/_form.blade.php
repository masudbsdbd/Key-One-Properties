@php
    $today = now(config('app.display_timezone'))->toDateString();
    $user = auth()->user();
    $types = collect(App\Models\InvestorCapitalTransaction::TYPES)->filter(fn ($l, $t) => $user->can($t === 'contribution' ? 'investor.capital.record' : 'investor.capital.return'))->all();
@endphp
<form method="POST" action="{{ route('investor-capital.store') }}" data-ajax-form class="space-y-5">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ $key }}">
    @if ($agreement)
        <input type="hidden" name="agreement" value="{{ $agreement->public_id }}">
        <div class="rounded-card border border-line bg-surface-muted px-4 py-3">
            <p class="font-medium text-ink-900">{{ $agreement->investor->name }} · {{ $agreement->project->code }} Unit {{ $agreement->flat->flat_no }}</p>
            <p class="text-[13px] text-ink-500">{{ $agreement->agreement_no }} · Committed <x-money :value="$agreement->committed_capital" /> · Invested <x-money :value="$balance" /></p>
        </div>
    @else
        <x-form.select name="agreement" label="Agreement" :url="route('options.investor-agreements')" required placeholder="Search agreement, investor or unit" />
    @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <x-form.select name="transaction_type" label="Type" :options="$types" :selected="$type" required :enhance="false" />
        <x-form.input name="amount" label="Amount" inputmode="decimal" required autofocus />
        <x-form.input name="transaction_date" type="date" label="Date" :value="$today" :max="$today" required />
        <x-form.select name="payment_method" label="Method" :options="App\Modules\Investors\Controllers\CapitalController::METHODS" selected="bank" required :enhance="false" />
        <x-form.input name="reference_no" label="Reference" wrapper-class="sm:col-span-2" />
    </div>
    <x-form.textarea name="notes" label="Notes" rows="2" />
    <x-form.actions class="mt-0">
        <x-button variant="secondary" data-modal-close>Cancel</x-button>
        <x-button type="submit" icon="check">Record</x-button>
    </x-form.actions>
</form>
