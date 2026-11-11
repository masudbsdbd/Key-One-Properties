@php $icons = ['cash' => 'wallet', 'bank' => 'bank', 'mobile' => 'phone']; @endphp
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat-card label="Total Balance" :value="App\Support\Format::money(App\Support\Money::add('0', ...$totals->values()->all()))" icon="calculator" tone="accent" />
    @foreach (App\Models\MoneyAccount::KINDS as $kind => $label)
        <x-stat-card :label="$label" :value="App\Support\Format::money($totals[$kind])" :icon="$icons[$kind]" :tone="['cash' => 'success', 'bank' => 'info', 'mobile' => 'violet'][$kind]" />
    @endforeach
</div>

@if ($accounts->isEmpty())
    <x-card class="mt-6">
        <x-empty-state icon="wallet" title="No accounts yet">
            @if ($canManage)<x-button icon="plus" data-remote-modal="{{ route('accounting.cash-bank.create') }}" data-size="max-w-2xl">New Account</x-button>@endif
        </x-empty-state>
    </x-card>
@else
    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($accounts as $account)
            <x-card @class(['opacity-60' => ! $account->is_active])>
                <div class="flex items-start gap-3">
                    <span class="icon-tile tone-accent shrink-0"><x-icon :name="$icons[$account->kind]" size="h-5 w-5" /></span>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('accounting.cash-bank.show', $account) }}" class="block truncate text-[15px] font-semibold text-ink-900 hover:text-accent-700">{{ $account->name }}</a>
                        <p class="truncate text-[13px] text-ink-500">{{ App\Models\MoneyAccount::KINDS[$account->kind] }}@if ($account->account_number) · {{ $account->account_number }}@endif</p>
                    </div>
                    @unless ($account->is_active)<x-badge tone="neutral">Inactive</x-badge>@endunless
                </div>
                <p @class(['mt-4 text-2xl font-semibold tabular-nums', 'text-ink-900' => App\Support\Money::cmp($account->balance, 0) >= 0, 'text-danger-600' => App\Support\Money::cmp($account->balance, 0) < 0])><x-money :value="$account->balance" /></p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($canManage && $account->is_active)
                        <x-button size="sm" variant="secondary" icon="arrow-down" data-remote-modal="{{ route('accounting.cash-bank.move', ['direction' => 'in', 'account' => $account->public_id]) }}" data-size="max-w-2xl">Deposit</x-button>
                        <x-button size="sm" variant="secondary" icon="arrow-up" data-remote-modal="{{ route('accounting.cash-bank.move', ['direction' => 'out', 'account' => $account->public_id]) }}" data-size="max-w-2xl">Withdraw</x-button>
                    @endif
                    <x-button size="sm" variant="ghost" :href="route('accounting.cash-bank.show', $account)">Statement</x-button>
                </div>
            </x-card>
        @endforeach
    </div>
@endif
