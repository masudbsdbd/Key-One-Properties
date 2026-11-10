@php
    $release = $bill->kind === 'retention_release';
    $history = collect([
        ['title' => 'Recorded', 'meta' => $bill->creator->name.' · '.App\Support\Format::datetime($bill->created_at), 'tone' => 'info'],
        $bill->posted_at ? ['title' => 'Approved', 'meta' => $bill->approver?->name.' · '.App\Support\Format::datetime($bill->posted_at), 'tone' => 'success'] : null,
    ])->filter()
        ->merge($bill->payments->map(fn ($p) => ['title' => 'Payment · '.$p->payment_no, 'meta' => App\Support\Format::date($p->payment_date).' · '.App\Support\Format::money($p->amount), 'body' => App\Models\ContractorPayment::STATUSES[$p->status], 'tone' => $p->status === 'approved' ? 'success' : 'neutral']))
        ->when($bill->reversed_at, fn ($c) => $c->push(['title' => 'Reversed', 'meta' => App\Support\Format::datetime($bill->reversed_at), 'body' => $bill->reversal_reason, 'tone' => 'danger']))
        ->values()->all();
@endphp
<x-layouts.app :title="$bill->bill_no">
    <x-page-header :title="$bill->bill_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Contractor Bills' => route('contractor-bills.index'), $bill->bill_no => null]">
        <x-slot:actions>
            <x-document-menu :url="route('contractor-bills.document', $bill)" />
            @if ($bill->status === 'draft')
                @can('cbill:approve', $bill)<x-button icon="check" data-action="post" data-url="{{ route('contractor-bills.approve', $bill) }}" data-reload data-confirm="Approve {{ $bill->bill_no }}?" data-confirm-message="{{ App\Support\Format::money($bill->chargeAmount()) }} will be added to {{ $bill->contractor->name }}'s payable{{ $release ? '' : ' and '.App\Support\Format::money($bill->costAmount()).' to project cost' }}." data-confirm-text="Approve" data-tone="primary">Approve</x-button>@endcan
                @can('cbill:manage', $bill)<x-button variant="ghost" icon="trash" data-action="post" data-url="{{ route('contractor-bills.cancel', $bill) }}" data-reload data-confirm="Discard draft {{ $bill->bill_no }}?" data-confirm-text="Discard" aria-label="Discard draft" />@endcan
            @endif
            @if ($bill->status === 'approved')
                @can('wo:pay', $bill->workOrder)<x-button icon="tag" data-remote-modal="{{ route('contractor-payments.create', ['work_order' => $bill->workOrder->public_id, 'bill' => $bill->public_id]) }}" data-size="max-w-2xl">Pay</x-button>@endcan
            @endif
            @if (in_array($bill->status, ['approved', 'paid'], true))
                @can('cbill:approve', $bill)<x-button variant="ghost" icon="restore" data-remote-modal="{{ route('contractor-bills.reverse.form', $bill) }}" data-size="max-w-md" :disabled="App\Support\Money::isPositive($paid)" data-disabled-reason="Reverse the payments on this bill first">Reverse</x-button>@endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <x-card>
                <div class="grid gap-5 sm:grid-cols-4">
                    <div class="sm:col-span-2"><p class="detail-label">Contractor</p><a href="{{ route('contractors.show', $bill->contractor) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $bill->contractor->name }}</a><p class="text-[13px] text-ink-500">{{ $bill->contractor->contractor_code }}</p></div>
                    <div class="sm:col-span-2"><p class="detail-label">Work Order</p><a href="{{ route('work-orders.show', $bill->workOrder) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $bill->workOrder->wo_no }}</a><p class="text-[13px] text-ink-500">{{ $bill->workOrder->title }} · <x-ref :to="$bill->project">{{ $bill->project->code }}</x-ref></p></div>
                    <div><p class="detail-label">Type</p><p class="detail-value">{{ App\Models\ContractorBill::KINDS[$bill->kind] }}</p></div>
                    <div><p class="detail-label">Bill Date</p><p class="detail-value">{{ App\Support\Format::date($bill->bill_date) }}</p></div>
                    <div><p class="detail-label">Status</p><x-status-badge :status="$bill->status" /></div>
                    <div><p class="detail-label">Outstanding</p><p class="detail-value font-semibold"><x-money :value="in_array($bill->status, ['approved', 'paid'], true) ? $outstanding : 0" /></p></div>
                </div>
                @if ($bill->notes)<p class="mt-4 border-t border-line pt-4 text-sm text-ink-700">{{ $bill->notes }}</p>@endif
            </x-card>

            @unless ($release)
                <x-card title="Measurements" :padded="false">
                    <table class="data-table">
                        <thead><tr><th>Work Done</th><th class="text-right">Qty</th><th class="text-right">Rate</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($bill->measurementLinks as $link)
                                <tr>
                                    <td><p class="text-ink-900">{{ $link->measurement->description }}</p><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($link->measurement->measurement_date) }}</p></td>
                                    <td class="whitespace-nowrap text-right tabular-nums">{{ App\Support\Format::qty($link->measurement->quantity) }} {{ $link->measurement->unit }}</td>
                                    <td class="text-right"><x-money :value="$link->measurement->rate" /></td>
                                    <td class="text-right font-medium"><x-money :value="$link->amount" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endunless

            <x-card title="Settlement">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-500">{{ $release ? 'Retention Released' : 'Gross' }}</dt><dd><x-money :value="$bill->gross_amount" /></dd></div>
                    @unless ($release)
                        @if ((float) $bill->advance_adjustment)
                            <div class="flex justify-between"><dt class="text-ink-500">Advance Adjustment</dt><dd><x-money :value="'-'.$bill->advance_adjustment" muted /></dd></div>
                        @endif
                        @if ((float) $bill->retention_amount)
                            <div class="flex justify-between"><dt class="text-ink-500">Retention</dt><dd><x-money :value="'-'.$bill->retention_amount" muted /></dd></div>
                        @endif
                        @if ((float) $bill->other_deductions)
                            <div class="flex justify-between"><dt class="text-ink-500">Other Deductions @if ($bill->deduction_note)<span class="text-ink-400">· {{ $bill->deduction_note }}</span>@endif</dt><dd><x-money :value="'-'.$bill->other_deductions" muted /></dd></div>
                        @endif
                    @endunless
                    <div class="flex justify-between border-t border-line pt-2 font-semibold text-ink-900"><dt>Net Payable</dt><dd><x-money :value="$bill->net_payable" /></dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Paid</dt><dd><x-money :value="$paid" /></dd></div>
                </dl>
            </x-card>

            <x-attachments :owner="$bill" type="contractor-bill" />
        </div>
        <x-card title="History" :padded="false"><x-timeline :items="$history" /></x-card>
    </div>
</x-layouts.app>
