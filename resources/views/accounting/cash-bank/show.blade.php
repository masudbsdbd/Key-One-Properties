<x-layouts.app :title="$account->name">
    <x-page-header :title="$account->name" :breadcrumbs="['Dashboard' => route('dashboard'), 'Cash & Bank' => route('accounting.cash-bank.index'), $account->name => null]">
        @can('accounting.cashbank.manage')
            <x-slot:actions>
                @if ($account->is_active)
                    <x-button variant="secondary" icon="arrow-down" data-remote-modal="{{ route('accounting.cash-bank.move', ['direction' => 'in', 'account' => $account->public_id]) }}" data-size="max-w-2xl">Deposit</x-button>
                    <x-button variant="secondary" icon="arrow-up" data-remote-modal="{{ route('accounting.cash-bank.move', ['direction' => 'out', 'account' => $account->public_id]) }}" data-size="max-w-2xl">Withdraw</x-button>
                    <x-button icon="repeat" data-remote-modal="{{ route('accounting.cash-bank.move', ['direction' => 'transfer', 'account' => $account->public_id]) }}" data-size="max-w-2xl">Transfer</x-button>
                @endif
                <x-button variant="ghost" icon="edit" data-remote-modal="{{ route('accounting.cash-bank.edit', $account) }}" data-size="max-w-2xl">Edit</x-button>
                @unless ($account->isUsed())
                    <x-button variant="ghost" icon="trash" data-action="delete" data-url="{{ route('accounting.cash-bank.destroy', $account) }}" data-confirm="Delete {{ $account->name }}?" data-confirm-text="Delete">Delete</x-button>
                @endunless
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Balance">
            <div data-partial-url="{{ route('accounting.cash-bank.balance', $account) }}">@include('accounting.cash-bank._balance')</div>
            <div class="mt-3">@if ($account->is_active)<x-badge tone="success">Active</x-badge>@else<x-badge tone="neutral">Inactive</x-badge>@endif</div>
        </x-card>
        <x-card class="min-w-0 xl:col-span-2">
            <div class="grid gap-5 sm:grid-cols-3">
                <div><p class="detail-label">Type</p><p class="detail-value">{{ App\Models\MoneyAccount::KINDS[$account->kind] }}</p></div>
                <div><p class="detail-label">Number</p><p class="detail-value tabular-nums">{{ $account->account_number ?: '—' }}</p></div>
                <div><p class="detail-label">Bank</p><p class="detail-value">{{ $account->bank_name ?: '—' }}@if ($account->branch) · {{ $account->branch }}@endif</p></div>
            </div>
            @if ($account->notes)<p class="mt-4 border-t border-line pt-4 text-sm text-ink-700">{{ $account->notes }}</p>@endif
        </x-card>
    </div>

    <h2 class="mb-3 mt-8 text-[15px] font-semibold text-ink-900">Statement</h2>
    <x-data-table data-table-history :url="route('accounting.cash-bank.table', ['account' => $account->public_id])" search="Search details, journal no or reference" :columns="[
        ['label' => 'Date'], ['label' => 'Details'], ['label' => 'In', 'class' => 'text-right'], ['label' => 'Out', 'class' => 'text-right'], ['label' => 'Balance', 'class' => 'text-right'], ['label' => '', 'class' => 'w-24'],
    ]">
        <x-slot:filters>
            <select name="direction" class="form-control w-36" data-table-filter aria-label="Direction"><option value="">In and out</option><option value="in">Money in</option><option value="out">Money out</option></select>
            <x-period />
        </x-slot:filters>
    </x-data-table>
</x-layouts.app>
