@php $draft = $reconciliations->firstWhere('status', 'draft'); @endphp
<x-layouts.app :title="'Reconcile '.$account->name">
    <x-page-header :title="'Reconcile '.$account->name" :breadcrumbs="['Dashboard' => route('dashboard'), 'Cash & Bank' => route('accounting.cash-bank.index'), $account->name => route('accounting.cash-bank.show', $account), 'Reconcile' => null]">
        @if ($draft)
            <x-slot:actions><x-button icon="arrow-right" :href="route('accounting.bank-reconciliation.show', $draft)">Continue · {{ App\Support\Format::date($draft->statement_date) }}</x-button></x-slot:actions>
        @endif
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="New Statement">
            <form method="POST" action="{{ route('accounting.bank-reconciliation.store', $account) }}" data-ajax-form class="space-y-5">
                @csrf
                <x-form.input name="statement_date" type="date" label="Statement Date" :value="App\Modules\Accounting\Services\Ledger::today()->toDateString()" required />
                <x-form.input name="statement_balance" label="Balance on the Statement (BDT)" inputmode="decimal" required />
                <div><p class="form-label">Balance in the Books Now</p><p class="detail-value tabular-nums"><x-money :value="$balance" /></p></div>
                <x-form.actions class="mt-0"><x-button type="submit" icon="arrow-right" :disabled="(bool) $draft" :data-disabled-reason="$draft ? 'Finish the one in progress first' : null">Start</x-button></x-form.actions>
            </form>
        </x-card>
        <x-card title="Statements" :padded="false" class="min-w-0 xl:col-span-2">
            @if ($reconciliations->isEmpty())
                <x-empty-state icon="check-square" title="Not reconciled yet" />
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Statement Date</th><th class="text-right">Statement Balance</th><th>Status</th><th class="w-24"></th></tr></thead>
                        <tbody>
                            @foreach ($reconciliations as $r)
                                <tr>
                                    <td class="font-medium text-ink-900">{{ App\Support\Format::date($r->statement_date) }}</td>
                                    <td class="text-right tabular-nums"><x-money :value="$r->statement_balance" /></td>
                                    <td><x-badge :tone="$r->status === 'completed' ? 'success' : 'warning'">{{ App\Models\BankReconciliation::STATUSES[$r->status] }}</x-badge>
                                        @if ($r->completer)<span class="ml-2 text-[13px] text-ink-500">{{ $r->completer->name }} · {{ App\Support\Format::date($r->completed_at) }}</span>@endif</td>
                                    <td class="text-right"><x-button size="sm" variant="ghost" :href="route('accounting.bank-reconciliation.show', $r)">{{ $r->status === 'draft' ? 'Continue' : 'Open' }}</x-button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
</x-layouts.app>
