@if (isset($data['supplier_aging']))
    <p class="mb-2 text-sm font-medium text-ink-900">Suppliers · {{ App\Support\Format::money($data['supplier_total']) }}</p>
    <x-bars :items="$data['supplier_aging']" tone="bg-warning-600" />
@endif
@if (isset($data['contractor_total']))
    <div class="mt-4">@include('executive.widgets._stat', ['label' => 'Contractor bills outstanding', 'value' => App\Support\Format::money($data['contractor_total'])])</div>
@endif
@include('executive.widgets._footer', ['basis' => 'Approved bills less approved payments'])
