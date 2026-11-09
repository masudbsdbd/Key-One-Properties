<x-layouts.portal title="Payments" portal="customer">
    <x-page-header title="Payments" :breadcrumbs="['Overview' => route('customer.dashboard'), 'Payments' => null]" />
    <x-data-table :url="route('customer.payments.table')" sort="date" dir="desc" search="Search payment or reference" :columns="[
        ['label' => 'Payment', 'sort' => 'date'], ['label' => 'Unit'], ['label' => 'Applied To'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'], ['label' => '', 'class' => 'w-12'],
    ]">
        <x-slot:filters>
            @if (count($units) > 1)
                <div class="w-60"><select name="unit" class="form-control" data-table-filter @if (count($units) > 7) data-select data-placeholder="All units" @endif aria-label="Unit"><option value="">All units</option>@foreach ($units as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
            @endif
        </x-slot:filters>
    </x-data-table>
    @if ($refunds->isNotEmpty())
        <x-card title="Refunds" :padded="false" class="mt-6">
            @foreach ($refunds as $r)
                <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0">
                    <span class="min-w-0 flex-1"><span class="block text-sm font-medium text-ink-900">{{ $r->refund_no }}</span><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($r->paid_on) }}</span></span>
                    <x-money :value="$r->payable()" class="text-sm font-medium" />
                    <x-badge :tone="$r->status === 'reversed' ? 'danger' : 'success'">{{ $r->status === 'reversed' ? 'Reversed' : 'Refunded' }}</x-badge>
                </div>
            @endforeach
        </x-card>
    @endif
</x-layouts.portal>
