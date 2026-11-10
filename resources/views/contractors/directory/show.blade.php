<x-layouts.app :title="$contractor->name">
    <x-page-header :title="$contractor->name" :breadcrumbs="['Dashboard' => route('dashboard'), 'Contractors' => route('contractors.index'), $contractor->contractor_code => null]">
        <x-slot:actions>
            @can('contractors.workorder.create')<x-button icon="plus" data-remote-modal="{{ route('work-orders.create', ['contractor' => $contractor->public_id]) }}" data-size="max-w-3xl">Work Order</x-button>@endcan
            @can('contractors.update')<x-button variant="ghost" icon="edit" data-remote-modal="{{ route('contractors.edit', $contractor) }}" data-size="max-w-2xl" aria-label="Edit contractor" />@endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Payable" :value="App\Support\Format::money($balance)" icon="tag" tone="warning">
            @if (App\Support\Money::isPositive($retention))
                <x-slot:meta><span class="text-ink-500">+ {{ App\Support\Format::money($retention) }} retention held</span></x-slot:meta>
            @endif
        </x-stat-card>
        <x-stat-card label="Billed" :value="App\Support\Format::money($billed)" icon="file" tone="info" />
        <x-stat-card label="Paid" :value="App\Support\Format::money($paid)" icon="check" tone="success">
            @if (App\Support\Money::isPositive($advance))
                <x-slot:meta><span class="text-ink-500">{{ App\Support\Format::money($advance) }} advance not yet adjusted</span></x-slot:meta>
            @endif
        </x-stat-card>
        <x-stat-card label="Active Work Orders" :value="$activeOrders" icon="check-square" tone="accent" />
    </div>

    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
        <button type="button" role="tab" class="tab" id="tab-orders" data-tab="orders" aria-controls="panel-orders">Work Orders</button>
        <button type="button" role="tab" class="tab" id="tab-bills" data-tab="bills" aria-controls="panel-bills">Bills</button>
        <button type="button" role="tab" class="tab" id="tab-payments" data-tab="payments" aria-controls="panel-payments">Payments</button>
        @can('contractors.ledger.view')<button type="button" role="tab" class="tab" id="tab-ledger" data-tab="ledger" aria-controls="panel-ledger">Ledger</button>@endcan
        <button type="button" role="tab" class="tab" id="tab-profile" data-tab="profile" aria-controls="panel-profile">Profile</button>
        <button type="button" role="tab" class="tab" id="tab-files" data-tab="files" aria-controls="panel-files">Files</button>
        <button type="button" role="tab" class="tab" id="tab-activity" data-tab="activity" aria-controls="panel-activity">Activity</button>
    </div>

    <section id="panel-orders" role="tabpanel" aria-labelledby="tab-orders">
        <x-data-table :url="route('work-orders.table', ['contractor' => $contractor->public_id])" sort="date" dir="desc" search="Search work order" :columns="[
            ['label' => 'Work Order', 'sort' => 'date'], ['label' => 'Project'], ['label' => 'Contract', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Progress'], ['label' => 'Status'],
        ]" />
    </section>
    <section id="panel-bills" role="tabpanel" aria-labelledby="tab-bills" hidden>
        <x-data-table :url="route('contractor-bills.table', ['contractor' => $contractor->public_id])" sort="date" dir="desc" search="Search bill number" :columns="[
            ['label' => 'Bill', 'sort' => 'date'], ['label' => 'Work Order'], ['label' => 'Gross', 'class' => 'text-right'], ['label' => 'Net Payable', 'class' => 'text-right', 'sort' => 'net'], ['label' => 'Outstanding', 'class' => 'text-right'], ['label' => 'Status'],
        ]" />
    </section>
    <section id="panel-payments" role="tabpanel" aria-labelledby="tab-payments" hidden>
        <x-data-table :url="route('contractor-payments.table', ['contractor' => $contractor->public_id])" sort="date" dir="desc" search="Search payment number or reference" :columns="[
            ['label' => 'Payment', 'sort' => 'date'], ['label' => 'Against'], ['label' => 'Method'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'],
        ]" />
    </section>
    @can('contractors.ledger.view')
        <section id="panel-ledger" role="tabpanel" aria-labelledby="tab-ledger" hidden>
            <x-data-table :url="route('contractors.ledger', $contractor)" :search="false" :columns="[
                ['label' => 'Date'], ['label' => 'Type'], ['label' => 'Description'], ['label' => 'Charge', 'class' => 'text-right'], ['label' => 'Payment', 'class' => 'text-right'], ['label' => 'Balance', 'class' => 'text-right'],
            ]">
                <x-slot:toolbar><x-export-menu :url="route('contractors.ledger.export', $contractor)" /></x-slot:toolbar>
            </x-data-table>
        </section>
    @endcan
    <section id="panel-profile" role="tabpanel" aria-labelledby="tab-profile" hidden>
        <x-card class="max-w-2xl">
            <div class="flex items-center gap-4">
                <x-avatar :name="$contractor->name" size="h-14 w-14 text-lg" />
                <div class="min-w-0"><p class="truncate font-semibold text-ink-900">{{ $contractor->name }}</p><p class="text-sm text-ink-500">{{ $contractor->contractor_code }}@if ($contractor->trade) · {{ $contractor->trade }}@endif</p><x-status-badge :status="$contractor->status" class="mt-1" /></div>
            </div>
            <dl class="mt-5 grid gap-3 border-t border-line pt-5 text-sm sm:grid-cols-2">
                <div class="flex gap-3"><x-icon name="users" size="h-4 w-4 mt-0.5 text-ink-400" /><dt class="sr-only">Contact</dt><dd class="text-ink-900">{{ $contractor->contact_person ?: '—' }}</dd></div>
                <div class="flex gap-3"><x-icon name="phone" size="h-4 w-4 mt-0.5 text-ink-400" /><dt class="sr-only">Mobile</dt><dd class="text-ink-900">{{ $contractor->mobile ? App\Support\Phone::display($contractor->mobile) : '—' }}</dd></div>
                <div class="flex gap-3"><x-icon name="mail" size="h-4 w-4 mt-0.5 text-ink-400" /><dt class="sr-only">Email</dt><dd class="break-all text-ink-900">{{ $contractor->email ?: '—' }}</dd></div>
                <div class="flex gap-3"><x-icon name="id-card" size="h-4 w-4 mt-0.5 text-ink-400" /><dt class="sr-only">TIN</dt><dd class="text-ink-900">{{ $contractor->tax_id ?: '—' }}</dd></div>
                <div class="flex gap-3 sm:col-span-2"><x-icon name="map-pin" size="h-4 w-4 mt-0.5 text-ink-400" /><dt class="sr-only">Address</dt><dd class="text-ink-900">{{ $contractor->address ?: '—' }}</dd></div>
            </dl>
        </x-card>
    </section>
    <section id="panel-files" role="tabpanel" aria-labelledby="tab-files" hidden>
        <x-attachments :owner="$contractor" type="contractor" />
    </section>
    <section id="panel-activity" role="tabpanel" aria-labelledby="tab-activity" hidden>
        <x-card title="Activity" :padded="false">
            <div data-partial-url="{{ route('contractors.activity', $contractor) }}" data-lazy><div class="space-y-3 p-5"><div class="skeleton h-12 w-full"></div></div></div>
        </x-card>
    </section>
</x-layouts.app>
