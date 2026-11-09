<div class="grid grid-cols-2 gap-3">
    @include('executive.widgets._stat', ['label' => 'Owned stock value', 'value' => App\Support\Format::money($data['owned_value'])])
    @include('executive.widgets._stat', ['label' => 'Low stock items', 'value' => $data['low_stock']])
    @include('executive.widgets._stat', ['label' => 'Borrowed-in lines', 'value' => $data['borrowed_in'], 'note' => 'Custody, not owned'])
    @include('executive.widgets._stat', ['label' => 'Lent-out lines', 'value' => $data['lent_out']])
    @include('executive.widgets._stat', ['label' => 'Open borrows', 'value' => $data['open_borrows']])
    @include('executive.widgets._stat', ['label' => 'Transfers in period', 'value' => $data['transfers']])
</div>
@include('executive.widgets._footer', ['basis' => 'Current balances'])
