<x-layouts.app title="Chart of Accounts">
    <x-page-header title="Chart of Accounts" :breadcrumbs="['Dashboard' => route('dashboard'), 'Accounting' => null, 'Chart of Accounts' => null]">
        @can('accounting.coa.manage')
            <x-slot:actions><x-button icon="plus" data-remote-modal="{{ route('accounting.accounts.create') }}" data-size="max-w-2xl">New Account</x-button></x-slot:actions>
        @endcan
    </x-page-header>
    <div data-partial-url="{{ route('accounting.accounts.list') }}" data-lazy>
        <x-card><div class="skeleton h-12 w-full"></div></x-card>
    </div>
</x-layouts.app>
