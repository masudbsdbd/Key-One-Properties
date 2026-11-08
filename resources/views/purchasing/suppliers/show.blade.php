@php($user = auth()->user())
<x-layouts.app :title="$supplier->name">
    <x-page-header :title="$supplier->name" :breadcrumbs="['Dashboard' => route('dashboard'), 'Suppliers' => route('suppliers.index'), $supplier->supplier_code => null]">
        <x-slot:actions>
            @can('purchases.create')<x-button icon="plus" data-remote-modal="{{ route('purchase-orders.create', ['supplier' => $supplier->public_id]) }}" data-size="max-w-5xl">Purchase Order</x-button>@endcan
            @can('purchases.payment.create')<x-button variant="secondary" icon="tag" data-remote-modal="{{ route('supplier-payments.create', ['supplier' => $supplier->public_id]) }}" data-size="max-w-3xl">Payment</x-button>@endcan
            @can('suppliers.update')<x-button variant="ghost" icon="edit" data-remote-modal="{{ route('suppliers.edit', $supplier) }}" data-size="max-w-2xl" aria-label="Edit supplier" />@endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Payable" :value="App\Support\Format::money($balance)" icon="tag" tone="warning" />
        <x-stat-card label="Billed" :value="App\Support\Format::money($billed)" icon="file" tone="info" />
        <x-stat-card label="Paid" :value="App\Support\Format::money($paid)" icon="check" tone="success" />
        <x-stat-card label="Open Orders" :value="$openOrders" icon="note" tone="accent" />
    </div>

    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
        <button type="button" role="tab" class="tab" id="tab-overview" data-tab="overview" aria-controls="panel-overview">Overview</button>
        <button type="button" role="tab" class="tab" id="tab-orders" data-tab="orders" aria-controls="panel-orders">Purchase Orders</button>
        <button type="button" role="tab" class="tab" id="tab-bills" data-tab="bills" aria-controls="panel-bills">Bills</button>
        <button type="button" role="tab" class="tab" id="tab-payments" data-tab="payments" aria-controls="panel-payments">Payments</button>
        @can('suppliers.ledger.view')<button type="button" role="tab" class="tab" id="tab-ledger" data-tab="ledger" aria-controls="panel-ledger">Ledger</button>@endcan
        <button type="button" role="tab" class="tab" id="tab-files" data-tab="files" aria-controls="panel-files">Files</button>
        <button type="button" role="tab" class="tab" id="tab-activity" data-tab="activity" aria-controls="panel-activity">Activity</button>
    </div>

    <section id="panel-overview" role="tabpanel" aria-labelledby="tab-overview">
        <div class="grid gap-6 xl:grid-cols-[1fr_2fr]">
            <x-card>
                <div class="flex items-center gap-4">
                    <x-avatar :name="$supplier->name" size="h-14 w-14 text-lg" />
                    <div class="min-w-0"><p class="truncate font-semibold text-ink-900">{{ $supplier->name }}</p><p class="text-sm text-ink-500">{{ $supplier->supplier_code }}</p><x-status-badge :status="$supplier->status" class="mt-1" /></div>
                </div>
                <dl class="mt-5 space-y-3 border-t border-line pt-5 text-sm">
                    <div class="flex gap-3"><x-icon name="users" size="h-4 w-4 mt-0.5 text-ink-400" /><dt class="sr-only">Contact</dt><dd class="text-ink-900">{{ $supplier->contact_person ?: '—' }}</dd></div>
                    <div class="flex gap-3"><x-icon name="phone" size="h-4 w-4 mt-0.5 text-ink-400" /><dt class="sr-only">Mobile</dt><dd class="text-ink-900">{{ $supplier->mobile ? App\Support\Phone::display($supplier->mobile) : '—' }}</dd></div>
                    <div class="flex gap-3"><x-icon name="mail" size="h-4 w-4 mt-0.5 text-ink-400" /><dt class="sr-only">Email</dt><dd class="break-all text-ink-900">{{ $supplier->email ?: '—' }}</dd></div>
                    <div class="flex gap-3"><x-icon name="map-pin" size="h-4 w-4 mt-0.5 text-ink-400" /><dt class="sr-only">Address</dt><dd class="text-ink-900">{{ $supplier->address ?: '—' }}</dd></div>
                    <div class="flex gap-3"><x-icon name="id-card" size="h-4 w-4 mt-0.5 text-ink-400" /><dt class="sr-only">TIN / BIN</dt><dd class="text-ink-900">{{ $supplier->tax_id ?: '—' }}</dd></div>
                </dl>
            </x-card>
            <x-card title="Payable Aging">
                @php($agingTotal = App\Support\Money::add(...array_values($aging)))
                <div class="grid gap-4 sm:grid-cols-4">
                    @foreach (['current' => '0–30 days', '31-60' => '31–60 days', '61-90' => '61–90 days', '90+' => 'Over 90 days'] as $bucket => $label)
                        <div class="rounded-card border border-line p-4">
                            <p class="text-[13px] text-ink-500">{{ $label }}</p>
                            <p class="mt-1 text-lg font-semibold {{ $bucket === '90+' && App\Support\Money::isPositive($aging[$bucket]) ? 'text-danger-600' : 'text-ink-900' }}"><x-money :value="$aging[$bucket]" /></p>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full bg-accent-500" style="width: {{ App\Support\Money::isPositive($agingTotal) ? round((float) $aging[$bucket] / (float) $agingTotal * 100) : 0 }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>
    </section>

    <section id="panel-orders" role="tabpanel" aria-labelledby="tab-orders" hidden>
        <x-data-table :url="route('purchase-orders.table', ['supplier' => $supplier->public_id])" sort="date" dir="desc" search="Search PO number" :columns="[
            ['label' => 'Order', 'sort' => 'date'], ['label' => 'Warehouse'], ['label' => 'Total', 'class' => 'text-right', 'sort' => 'total'], ['label' => 'Received'], ['label' => 'Status'],
        ]" />
    </section>
    <section id="panel-bills" role="tabpanel" aria-labelledby="tab-bills" hidden>
        <x-data-table :url="route('supplier-bills.table', ['supplier' => $supplier->public_id])" sort="date" dir="desc" search="Search bill or invoice number" :columns="[
            ['label' => 'Bill', 'sort' => 'date'], ['label' => 'Due', 'sort' => 'due'], ['label' => 'Total', 'class' => 'text-right', 'sort' => 'total'], ['label' => 'Outstanding', 'class' => 'text-right'], ['label' => 'Status'],
        ]" />
    </section>
    <section id="panel-payments" role="tabpanel" aria-labelledby="tab-payments" hidden>
        <x-data-table :url="route('supplier-payments.table', ['supplier' => $supplier->public_id])" sort="date" dir="desc" search="Search payment number or reference" :columns="[
            ['label' => 'Payment', 'sort' => 'date'], ['label' => 'Method'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'],
        ]" />
    </section>
    @can('suppliers.ledger.view')
        <section id="panel-ledger" role="tabpanel" aria-labelledby="tab-ledger" hidden>
            <x-data-table :url="route('suppliers.ledger', $supplier)" :search="false" :columns="[
                ['label' => 'Date'], ['label' => 'Type'], ['label' => 'Description'], ['label' => 'Charge', 'class' => 'text-right'], ['label' => 'Payment', 'class' => 'text-right'], ['label' => 'Balance', 'class' => 'text-right'],
            ]">
                @can('suppliers.ledger.export')
                    <x-slot:toolbar><a href="{{ route('suppliers.ledger.export', $supplier) }}" class="btn btn-secondary"><x-icon name="download" size="h-4 w-4" />CSV</a></x-slot:toolbar>
                @endcan
            </x-data-table>
        </section>
    @endcan
    <section id="panel-files" role="tabpanel" aria-labelledby="tab-files" hidden>
        <x-attachments :owner="$supplier" type="supplier" />
    </section>
    <section id="panel-activity" role="tabpanel" aria-labelledby="tab-activity" hidden>
        <x-card title="Activity" :padded="false">
            <div data-partial-url="{{ route('suppliers.activity', $supplier) }}" data-lazy><div class="space-y-3 p-5"><div class="skeleton h-12 w-full"></div></div></div>
        </x-card>
    </section>
</x-layouts.app>
