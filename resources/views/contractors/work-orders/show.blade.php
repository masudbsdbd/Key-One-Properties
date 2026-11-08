@php
    $s = $order->status;
    $remaining = App\Support\Money::sub($order->contract_amount, $measured);
    $pct = (float) $order->contract_amount > 0 ? min(100, round((float) $measured / (float) $order->contract_amount * 100, 1)) : 0;
    $canMeasureApprove = Gate::allows('wo:measure-approve', $order);
    $hasApproved = $order->measurements->contains('status', 'approved');
    $history = collect([
        ['title' => 'Created', 'meta' => $order->creator->name.' · '.App\Support\Format::datetime($order->created_at), 'tone' => 'info'],
        $order->approved_at ? ['title' => 'Approved', 'meta' => $order->approver?->name.' · '.App\Support\Format::datetime($order->approved_at), 'tone' => 'success'] : null,
    ])->filter()
        ->merge($order->bills->whereIn('status', ['approved', 'paid'])->map(fn ($b) => ['title' => App\Models\ContractorBill::KINDS[$b->kind].' · '.$b->bill_no, 'meta' => App\Support\Format::date($b->bill_date).' · '.App\Support\Format::money($b->net_payable), 'tone' => 'accent']))
        ->merge($order->payments->where('status', 'approved')->map(fn ($p) => ['title' => ($p->bill_id ? 'Payment · ' : 'Advance · ').$p->payment_no, 'meta' => App\Support\Format::date($p->payment_date).' · '.App\Support\Format::money($p->amount), 'tone' => 'success']))
        ->when($order->completed_at, fn ($c) => $c->push(['title' => 'Completed', 'meta' => App\Support\Format::datetime($order->completed_at), 'tone' => 'success']))
        ->when($s === 'cancelled', fn ($c) => $c->push(['title' => 'Cancelled', 'meta' => App\Support\Format::datetime($order->updated_at), 'body' => $order->status_reason, 'tone' => 'danger']))
        ->values()->all();
@endphp
<x-layouts.app :title="$order->wo_no">
    <x-page-header :title="$order->wo_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Work Orders' => route('work-orders.index'), $order->wo_no => null]">
        <x-slot:actions>
            @if ($s === 'draft')
                @can('wo:approve', $order)<x-button icon="check" data-action="post" data-url="{{ route('work-orders.approve', $order) }}" data-reload data-confirm="Approve {{ $order->wo_no }}?" data-confirm-message="Contract {{ App\Support\Format::money($order->contract_amount) }} with {{ $order->contractor->name }}." data-confirm-text="Approve" data-tone="primary">Approve</x-button>@endcan
                @can('wo:edit', $order)<x-button variant="secondary" icon="edit" data-remote-modal="{{ route('work-orders.edit', $order) }}" data-size="max-w-3xl">Edit</x-button>@endcan
            @endif
            @if ($s === 'approved')
                @can('wo:measure', $order)<x-button icon="plus" data-remote-modal="{{ route('work-orders.measurements.create', $order) }}" data-size="max-w-3xl">Measurement</x-button>@endcan
                @can('wo:bill', $order)
                    <x-button variant="secondary" icon="file" data-remote-modal="{{ route('work-orders.bills.create', $order) }}" data-size="max-w-3xl" :disabled="! $hasApproved" data-disabled-reason="No approved measurements to bill">Bill</x-button>
                @endcan
                @can('wo:pay', $order)<x-button variant="secondary" icon="tag" data-remote-modal="{{ route('contractor-payments.create', ['work_order' => $order->public_id]) }}" data-size="max-w-2xl">Pay</x-button>@endcan
                @can('wo:bill', $order)<x-button variant="ghost" icon="check-square" data-action="post" data-url="{{ route('work-orders.settle', $order) }}" data-reload data-confirm="Settle {{ $order->wo_no }}?" data-confirm-message="{{ App\Support\Money::isPositive($retention) ? 'A retention release bill of '.App\Support\Format::money($retention).' will be created.' : 'The work order will be completed.' }}" data-confirm-text="Settle" data-tone="primary">Settle</x-button>@endcan
            @endif
            @if (in_array($s, ['draft', 'approved'], true))
                @can('wo:approve', $order)<x-button variant="ghost" icon="x" data-remote-modal="{{ route('work-orders.cancel.form', $order) }}" data-size="max-w-md" aria-label="Cancel work order" />@endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <x-card>
                <div class="grid gap-5 sm:grid-cols-4">
                    <div class="sm:col-span-2"><p class="detail-label">Contractor</p><a href="{{ route('contractors.show', $order->contractor) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $order->contractor->name }}</a><p class="text-[13px] text-ink-500">{{ $order->contractor->trade ?: $order->contractor->contractor_code }}</p></div>
                    <div class="sm:col-span-2"><p class="detail-label">Project</p><a href="{{ route('projects.show', $order->project) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $order->project->name }}</a><p class="text-[13px] text-ink-500">{{ $order->workPackage?->label() ?? $order->project->code }}</p></div>
                    <div class="sm:col-span-2"><p class="detail-label">Title</p><p class="detail-value">{{ $order->title }}</p></div>
                    <div><p class="detail-label">Period</p><p class="detail-value">{{ App\Support\Format::date($order->start_date) }} – {{ App\Support\Format::date($order->end_date) }}</p></div>
                    <div><p class="detail-label">Status</p><x-status-badge :status="$s" /></div>
                </div>
                @if ($order->scope)<p class="mt-4 whitespace-pre-line border-t border-line pt-4 text-sm text-ink-700">{{ $order->scope }}</p>@endif
            </x-card>

            <x-card>
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div><p class="detail-label">Measured</p><p class="text-2xl font-semibold text-ink-900"><x-money :value="$measured" /></p><p class="text-[13px] text-ink-500">of <x-money :value="$order->contract_amount" /> · {{ $pct }}%</p></div>
                    <div class="text-right"><p class="detail-label">Remaining</p><p class="font-semibold text-ink-900"><x-money :value="$remaining" /></p></div>
                </div>
                <x-progress :value="$pct" class="mt-4" :label="false" />
                <dl class="mt-5 grid gap-4 border-t border-line pt-4 sm:grid-cols-4">
                    <div><dt class="detail-label">Billed</dt><dd class="detail-value"><x-money :value="$billed" /></dd></div>
                    <div><dt class="detail-label">Paid</dt><dd class="detail-value"><x-money :value="$paid" /></dd></div>
                    <div><dt class="detail-label">Advance Available</dt><dd class="detail-value"><x-money :value="$advance" /></dd></div>
                    <div><dt class="detail-label">Retention Held</dt><dd class="detail-value"><x-money :value="$retention" /> @if ((float) $order->retention_percent)<span class="text-[13px] text-ink-500">({{ rtrim(rtrim($order->retention_percent, '0'), '.') }}%)</span>@endif</dd></div>
                </dl>
            </x-card>

            <x-card title="Measurements" :padded="false">
                @if ($order->measurements->isEmpty())
                    <x-empty-state icon="note" title="No measurements" />
                @else
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>Work Done</th><th class="text-right">Qty</th><th class="text-right">Rate</th><th class="text-right">Amount</th><th>Status</th><th class="w-24"></th></tr></thead>
                            <tbody>
                                @foreach ($order->measurements->sortByDesc('measurement_date') as $m)
                                    <tr>
                                        <td class="min-w-56"><p class="font-medium text-ink-900">{{ $m->description }}</p><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($m->measurement_date) }} · {{ $m->submitter->name }}</p>@if ($m->rejection_reason)<p class="text-[13px] text-danger-600">{{ $m->rejection_reason }}</p>@endif</td>
                                        <td class="whitespace-nowrap text-right tabular-nums">{{ App\Support\Format::qty($m->quantity) }} {{ $m->unit }}</td>
                                        <td class="text-right"><x-money :value="$m->rate" /></td>
                                        <td class="text-right font-medium"><x-money :value="$m->measured_amount" /></td>
                                        <td><x-status-badge :status="$m->status" /></td>
                                        <td class="whitespace-nowrap text-right">
                                            @if ($m->status === 'draft' && $canMeasureApprove)
                                                <button type="button" class="btn btn-ghost btn-icon text-success-600" data-action="post" data-url="{{ route('work-orders.measurements.approve', [$order, $m]) }}" data-reload aria-label="Approve measurement"><x-icon name="check" size="h-4 w-4" /></button>
                                                <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-remote-modal="{{ route('work-orders.measurements.reject.form', [$order, $m]) }}" data-size="max-w-md" aria-label="Reject measurement"><x-icon name="x" size="h-4 w-4" /></button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            <div>
                <h2 class="card-title mb-3">Bills</h2>
                <x-data-table :url="route('contractor-bills.table', ['work_order' => $order->public_id])" sort="date" dir="desc" :search="false" :columns="[
                    ['label' => 'Bill', 'sort' => 'date'], ['label' => 'Gross', 'class' => 'text-right'], ['label' => 'Net Payable', 'class' => 'text-right'], ['label' => 'Outstanding', 'class' => 'text-right'], ['label' => 'Status'],
                ]" />
            </div>

            <x-attachments :owner="$order" type="work-order" />
        </div>

        <x-card title="History" :padded="false"><x-timeline :items="$history" /></x-card>
    </div>
</x-layouts.app>
