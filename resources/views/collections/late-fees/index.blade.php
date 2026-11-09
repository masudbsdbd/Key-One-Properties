<x-layouts.app title="Late Fees">
    <x-page-header title="Late Fees" :breadcrumbs="['Dashboard' => route('dashboard'), 'Collections' => route('collections.overview'), 'Late Fees' => null]">
        <x-slot:actions>@can('collections.latefee.manage')<x-button icon="plus" data-remote-modal="{{ route('late-fees.create') }}" data-size="max-w-2xl" data-auto-create>New Policy</x-button>@endcan</x-slot:actions>
    </x-page-header>
    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
        <button type="button" role="tab" class="tab" id="tab-policies" data-tab="policies" aria-controls="panel-policies">Customer Policies</button>
        <button type="button" role="tab" class="tab" id="tab-charges" data-tab="charges" aria-controls="panel-charges">Charges</button>
    </div>
    <section id="panel-policies" role="tabpanel" aria-labelledby="tab-policies">
        <x-data-table :url="route('late-fees.policies')" sort="from" dir="desc" search="Search customer" :columns="[
            ['label' => 'Customer'], ['label' => 'Applies To'], ['label' => 'Fee'], ['label' => 'Grace'], ['label' => 'Frequency'], ['label' => 'Effective', 'sort' => 'from'],
        ]" />
    </section>
    <section id="panel-charges" role="tabpanel" aria-labelledby="tab-charges" hidden>
        <x-data-table summary :url="route('late-fees.assessments')" sort="date" dir="desc" :search="false" :columns="[
            ['label' => 'Charged', 'sort' => 'date'], ['label' => 'Customer'], ['label' => 'Installment'], ['label' => 'Amount', 'class' => 'text-right'], ['label' => 'Status'], ['label' => '', 'class' => 'w-16'],
        ]">
            <x-slot:filters><select name="status" class="form-control w-36" data-table-filter aria-label="Status"><option value="">All</option>@foreach (App\Models\LateFeeAssessment::STATUSES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select><x-period /></x-slot:filters>
            <x-slot:toolbar>@can('collections.payment.statement.export')<x-export-menu :url="route('late-fees.export')" />@endcan</x-slot:toolbar>
        </x-data-table>
    </section>
</x-layouts.app>
