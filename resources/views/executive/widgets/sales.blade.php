<div class="grid grid-cols-2 gap-3">
    @include('executive.widgets._stat', ['label' => 'Bookings', 'value' => $data['bookings']])
    @include('executive.widgets._stat', ['label' => 'Sales', 'value' => $data['sales']])
    @include('executive.widgets._stat', ['label' => 'Net sales', 'value' => App\Support\Format::money($data['net_sales'])])
    @include('executive.widgets._stat', ['label' => 'Cancellations', 'value' => $data['cancellations']])
    @include('executive.widgets._stat', ['label' => 'Refunds paid', 'value' => App\Support\Format::money($data['refunds'])])
    @include('executive.widgets._stat', ['label' => 'Price changes', 'value' => $data['price_changes']])
</div>
@include('executive.widgets._footer', ['basis' => 'Approved in period'])
