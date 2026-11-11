<p class="detail-label">Cheque {{ $cheque->cheque_no }}@if ($cheque->bank_name) · {{ $cheque->bank_name }}@endif</p>
<div class="mt-1 flex flex-wrap items-start justify-between gap-3">
    <div>@include('accounting.cheques._status')</div>
    @can('accounting.cheque.manage')@include('accounting.cheques._buttons')@endcan
</div>
