<div class="grid grid-cols-2 gap-3">
    @include('executive.widgets._stat', ['label' => 'Collected in period', 'value' => App\Support\Format::money($data['collected'])])
    @include('executive.widgets._stat', ['label' => 'Receivable', 'value' => App\Support\Format::money($data['receivable']), 'note' => 'Active sales, statement balance'])
    @include('executive.widgets._stat', ['label' => 'Overdue', 'value' => App\Support\Format::money($data['overdue'])])
    @include('executive.widgets._stat', ['label' => 'Due next 30 days', 'value' => App\Support\Format::money($data['next_30'])])
    @include('executive.widgets._stat', ['label' => 'Unapplied advances', 'value' => App\Support\Format::money($data['advances'])])
    @include('executive.widgets._stat', ['label' => 'Late fees', 'value' => App\Support\Format::money($data['fees_billed']), 'note' => 'Collected '.App\Support\Format::money($data['fees_collected'])])
</div>
@include('executive.widgets._footer', ['basis' => 'Posted payments; billed and collected kept apart'])
