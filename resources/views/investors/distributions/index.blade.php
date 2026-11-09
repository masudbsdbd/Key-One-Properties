<x-layouts.app title="Distributions">
    <x-page-header title="Profit Distributions" :breadcrumbs="['Dashboard' => route('dashboard'), 'Investors' => route('investors.index'), 'Distributions' => null]" />
    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
        <button type="button" role="tab" class="tab" id="tab-payables" data-tab="payables" aria-controls="panel-payables">Payables</button>
        <button type="button" role="tab" class="tab" id="tab-distributions" data-tab="distributions" aria-controls="panel-distributions">Distributions</button>
    </div>
    <section id="panel-payables" role="tabpanel" aria-labelledby="tab-payables">
        <x-data-table :url="route('distributions.payables')" :search="false" :columns="[
            ['label' => 'Investor'], ['label' => 'Unit'], ['label' => 'Payable', 'class' => 'text-right'], ['label' => 'Paid', 'class' => 'text-right'], ['label' => 'Due', 'class' => 'text-right'], ['label' => 'Available', 'class' => 'text-right'], ['label' => '', 'class' => 'w-32'],
        ]">
            <x-slot:filters>
                <div class="w-56"><select name="investor" class="form-control" data-table-filter data-select data-select-url="{{ route('options.investors') }}" data-placeholder="All investors" aria-label="Investor"><option value=""></option></select></div>
            </x-slot:filters>
        </x-data-table>
    </section>
    <section id="panel-distributions" role="tabpanel" aria-labelledby="tab-distributions" hidden>
        <x-data-table summary :url="route('distributions.table')" sort="date" dir="desc" search="Search distribution or investor" :columns="[
            ['label' => 'Distribution', 'sort' => 'date'], ['label' => 'Investor'], ['label' => 'Unit'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'],
        ]">
            <x-slot:filters>
                <select name="status" class="form-control w-44" data-table-filter aria-label="Status"><option value="">All statuses</option>@foreach (App\Models\InvestorProfitDistribution::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                <x-period />
            </x-slot:filters>
            <x-slot:toolbar>@can('investor.ledger.export')<x-export-menu :url="route('distributions.export')" />@endcan</x-slot:toolbar>
        </x-data-table>
    </section>
</x-layouts.app>
