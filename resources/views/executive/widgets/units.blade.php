<x-bars :items="$data['counts']" :money="false" tone="bg-info-600" />
<div class="mt-4 grid grid-cols-2 gap-3">
    @include('executive.widgets._stat', ['label' => 'Registered', 'value' => $data['registered']])
    @include('executive.widgets._stat', ['label' => 'Handed over', 'value' => $data['handed_over']])
    @include('executive.widgets._stat', ['label' => 'Available list value', 'value' => App\Support\Format::money($data['available_value'])])
    @include('executive.widgets._stat', ['label' => 'Sold value', 'value' => App\Support\Format::money($data['sold_value'])])
</div>
@include('executive.widgets._footer', ['basis' => 'Current status'])
