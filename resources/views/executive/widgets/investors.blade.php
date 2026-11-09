<div class="grid grid-cols-2 gap-3">
    @include('executive.widgets._stat', ['label' => 'Capital received', 'value' => App\Support\Format::money($data['capital_received'])])
    @include('executive.widgets._stat', ['label' => 'Pending approval', 'value' => App\Support\Format::money($data['pending_amount']), 'note' => $data['pending_count'].' calculations'])
    @include('executive.widgets._stat', ['label' => 'Approved, not yet paid', 'value' => App\Support\Format::money($data['approved_due'])])
    @include('executive.widgets._stat', ['label' => 'Paid in period', 'value' => App\Support\Format::money($data['paid'])])
</div>
@include('executive.widgets._footer', ['basis' => 'Investor ledger; pending amounts are not payable'])
