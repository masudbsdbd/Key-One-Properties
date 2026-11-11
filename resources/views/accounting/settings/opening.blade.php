<x-layouts.app title="Opening Balances">
    <x-page-header title="Opening Balances" :breadcrumbs="['Dashboard' => route('dashboard'), 'Accounting Settings' => route('accounting.settings.edit'), 'Opening Balances' => null]">
        @if ($journal && auth()->user()->can('accounting.journal.view'))
            <x-slot:actions><x-button variant="secondary" icon="file" :href="route('accounting.journals.show', $journal)">{{ $journal->journal_no }}</x-button></x-slot:actions>
        @endif
    </x-page-header>

    <form method="POST" action="{{ route('accounting.opening.update') }}" data-ajax-form data-opening-form class="space-y-6">
        @csrf
        @method('PUT')
        <x-card>
            <div class="grid gap-5 md:grid-cols-4">
                <x-form.input name="date" type="date" label="Balances As Of" :value="$date->toDateString()" required />
                <div><p class="form-label">Assets</p><p class="detail-value text-base font-semibold tabular-nums" data-opening-total="asset">0</p></div>
                <div><p class="form-label">Liabilities + Equity</p><p class="detail-value text-base font-semibold tabular-nums" data-opening-total="other">0</p></div>
                <div><p class="form-label">Goes to Opening Balance Equity</p><p class="detail-value text-base font-semibold tabular-nums" data-opening-total="difference">0</p></div>
            </div>
        </x-card>

        @if ($moneyAccounts->isNotEmpty())
            <x-card title="Cash & Bank" :padded="false">
                <x-slot:actions><x-button size="sm" variant="ghost" :href="route('accounting.cash-bank.index')">Cash & Bank</x-button></x-slot:actions>
                <ul class="divide-y divide-line">
                    @foreach ($moneyAccounts as $m)
                        <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm"><a href="{{ route('accounting.cash-bank.show', $m) }}" class="ref-link font-medium">{{ $m->label() }}</a><span class="text-ink-500">{{ App\Models\MoneyAccount::KINDS[$m->kind] }}</span></li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        @foreach (['asset' => 'Assets', 'liability' => 'Liabilities', 'equity' => 'Equity'] as $category => $label)
            <x-card :title="$label" :padded="false">
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th class="w-28">Code</th><th>Account</th><th class="w-56 text-right">Balance (BDT)</th></tr></thead>
                        <tbody>
                            @foreach ($accounts[$category] ?? [] as $account)
                                <tr>
                                    <td class="tabular-nums text-ink-600">{{ $account->code }}</td>
                                    <td class="font-medium text-ink-900">{{ $account->name }}</td>
                                    <td><input type="text" inputmode="decimal" name="amounts[{{ $account->public_id }}]" value="{{ $account->opening !== null ? rtrim(rtrim($account->opening, '0'), '.') : '' }}" class="form-control text-right tabular-nums" aria-label="{{ $account->name }} balance" data-opening-amount="{{ $category === 'asset' ? 'asset' : 'other' }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endforeach
        <p class="form-error" data-form-error hidden></p>

        <x-form.actions class="mt-0">
            <x-button variant="secondary" :href="route('accounting.settings.edit')">Cancel</x-button>
            <x-button type="submit" icon="check">Save Opening Balances</x-button>
        </x-form.actions>
    </form>
</x-layouts.app>
