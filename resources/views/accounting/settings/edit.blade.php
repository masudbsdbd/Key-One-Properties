@php
    $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => Carbon\Carbon::create(2000, $m, 1)->format('F')]);
    $keyLabels = fn (string $key) => ['wip' => 'Work in Progress', 'grni' => 'Goods Received Not Billed', 'sales' => 'Property Sales'][$key] ?? Illuminate\Support\Str::headline($key);
@endphp
<x-layouts.app title="Accounting Settings">
    <x-page-header title="Accounting Settings" :breadcrumbs="['Dashboard' => route('dashboard'), 'Accounting' => null, 'Settings' => null]">
        @can('accounting.openingbalance.manage')
            <x-slot:actions><x-button variant="secondary" icon="book" :href="route('accounting.opening.edit')">Opening Balances</x-button></x-slot:actions>
        @endcan
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('accounting.settings.update') }}" data-ajax-form>
            @csrf
            @method('PUT')
            <x-card title="Accounts">
                <x-slot:actions>@if ($enabled)<x-badge tone="success">On</x-badge>@else<x-badge tone="neutral">Off</x-badge>@endif</x-slot:actions>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.select name="enabled" label="Keep the Accounts" :options="['1' => 'On', '0' => 'Off']" :selected="$enabled ? '1' : '0'" required :enhance="false" />
                    <x-form.input name="start_date" type="date" label="Accounts Start From" :value="$startDate?->toDateString() ?? $firstRecord" />
                    <x-form.select name="fiscal_start_month" label="Fiscal Year Starts" :options="$months" :selected="$fiscalMonth" required />
                    <div>
                        <p class="form-label">First Transaction on Record</p>
                        <p class="detail-value">{{ $firstRecord ? App\Support\Format::date(Carbon\Carbon::parse($firstRecord)) : '—' }}</p>
                    </div>
                </div>
                @if ($backlog)
                    <p class="mt-4 rounded-card border border-warning-500/30 bg-warning-50 px-4 py-3 text-sm text-warning-600">{{ $backlog }} transactions are waiting to be posted.
                        @if (Route::has('accounting.reconciliation.index'))<a href="{{ route('accounting.reconciliation.index') }}" class="font-semibold underline">Open Reconciliation Centre</a>@endif
                    </p>
                @endif
                <x-form.actions><x-button type="submit" icon="check">Save</x-button></x-form.actions>
            </x-card>
        </form>

        <form method="POST" action="{{ route('accounting.settings.defaults') }}" data-ajax-form>
            @csrf
            @method('PUT')
            <x-card title="Accounts for Payments with No Account">
                <div class="grid gap-5 sm:grid-cols-3">
                    @foreach (App\Models\MoneyAccount::KINDS as $kind => $label)
                        <x-form.select name="defaults[{{ $kind }}]" :id="'default-'.$kind" :label="$label" :options="($accounts[$kind] ?? collect())->mapWithKeys(fn ($a) => [$a->public_id => $a->label()])" :selected="$defaults[$kind]" placeholder="None" />
                    @endforeach
                </div>
                <x-form.actions><x-button type="submit" icon="check">Save</x-button></x-form.actions>
            </x-card>
        </form>
    </div>

    <x-card title="Which Account Each Transaction Goes To" :padded="false" class="mt-6">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Used For</th><th>Account</th><th class="w-28"></th></tr></thead>
                <tbody>
                    @foreach ($mapped as $account)
                        <tr>
                            <td class="font-medium text-ink-900">{{ $keyLabels($account->system_key) }}</td>
                            <td class="text-ink-700">{{ $account->label() }} <span class="text-[13px] text-ink-500">· {{ App\Modules\Accounting\Support\ChartOfAccounts::CATEGORIES[$account->category] }}</span></td>
                            <td class="text-right"><x-button size="sm" variant="ghost" data-remote-modal="{{ route('accounting.settings.mapping.form', ['key' => $account->system_key]) }}" data-size="max-w-md">Change</x-button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.app>
