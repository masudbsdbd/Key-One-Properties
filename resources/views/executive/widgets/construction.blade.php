<x-bars :items="$data['by_category']" />
<div class="mt-4 grid grid-cols-2 gap-3">
    @include('executive.widgets._stat', ['label' => 'Project cost in period', 'value' => App\Support\Format::money($data['project_total'])])
    @include('executive.widgets._stat', ['label' => 'Shared pool (approved runs)', 'value' => App\Support\Format::money($data['shared_pool'])])
    @if ($data['general_expenses'] !== null)@include('executive.widgets._stat', ['label' => 'General expenses', 'value' => App\Support\Format::money($data['general_expenses']), 'note' => 'Not charged to projects'])@endif
</div>
@include('executive.widgets._footer', ['basis' => 'Posted cost rows'])
