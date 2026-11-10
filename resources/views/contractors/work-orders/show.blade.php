@php
    $s = $order->status;
    $remaining = App\Support\Money::sub(App\Support\Money::sub($order->contract_amount, $measured), $pendingMeasured ?? '0');
    $pct = (float) $order->contract_amount > 0 ? min(100, round((float) $measured / (float) $order->contract_amount * 100, 1)) : 0;
    $canMeasureApprove = Gate::allows('wo:measure-approve', $order);
    $hasApproved = $order->measurements->contains('status', 'approved');
    $hasDraft = $order->measurements->contains('status', 'draft');
    $money = fn ($v) => App\Support\Format::money($v);
    $settleMessage = App\Support\Money::isPositive($retention) ? 'A retention release bill of '.$money($retention).' will be created'.(App\Support\Money::isPositive($advance) ? ', less '.$money($advance).' of unused advance' : '').'.' : 'The work order will be completed.';
    // One clear next step, worked out from where the work order stands; everything else sits in More.
    $next = match (true) {
        $s === 'draft' && Gate::allows('wo:approve', $order) => 'approve',
        $s !== 'approved' => null,
        $hasDraft && $canMeasureApprove => 'review',
        $hasApproved && Gate::allows('wo:bill', $order) => 'bill',
        $nextBill !== null && Gate::allows('wo:pay', $order) => 'pay',
        ! App\Support\Money::isPositive($remaining) && Gate::allows('wo:bill', $order) => 'settle',
        Gate::allows('wo:measure', $order) => 'measure',
        default => null,
    };
    $history = collect([
        ['title' => 'Created', 'meta' => $order->creator->name.' · '.App\Support\Format::datetime($order->created_at), 'tone' => 'info'],
        $order->approved_at ? ['title' => 'Approved', 'meta' => $order->approver?->name.' · '.App\Support\Format::datetime($order->approved_at), 'tone' => 'success'] : null,
    ])->filter()
        ->merge($order->bills->whereIn('status', ['approved', 'paid'])->map(fn ($b) => ['title' => App\Models\ContractorBill::KINDS[$b->kind].' · '.$b->bill_no, 'meta' => App\Support\Format::date($b->bill_date).' · '.App\Support\Format::money($b->net_payable), 'tone' => 'accent']))
        ->merge($order->payments->where('status', 'approved')->map(fn ($p) => ['title' => ($p->isRefund() ? 'Advance Refund · ' : ($p->bill_id ? 'Payment · ' : 'Advance · ')).$p->payment_no, 'meta' => App\Support\Format::date($p->payment_date).' · '.App\Support\Format::money($p->amount), 'tone' => 'success']))
        ->merge($order->variations->where('status', 'approved')->map(fn ($v) => ['title' => 'Variation · '.$v->variation_no, 'meta' => $v->approver?->name.' · '.App\Support\Format::datetime($v->approved_at), 'body' => App\Support\Format::money($v->old_amount).' → '.App\Support\Format::money($v->new_amount).' · '.$v->reason, 'tone' => 'warning']))
        ->when($order->completed_at, fn ($c) => $c->push(['title' => 'Completed', 'meta' => App\Support\Format::datetime($order->completed_at), 'tone' => 'success']))
        ->when($s === 'cancelled', fn ($c) => $c->push(['title' => 'Cancelled', 'meta' => App\Support\Format::datetime($order->updated_at), 'body' => $order->status_reason, 'tone' => 'danger']))
        ->values()->all();
@endphp
<x-layouts.app :title="$order->wo_no">
    <x-page-header :title="$order->wo_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Work Orders' => route('work-orders.index'), $order->wo_no => null]">
        <x-slot:actions>
            <x-document-menu :url="route('work-orders.document', $order)" />
            @switch($next)
                @case('approve')
                    <x-button icon="check" data-action="post" data-url="{{ route('work-orders.approve', $order) }}" data-reload data-confirm="Approve {{ $order->wo_no }}?" data-confirm-message="Contract {{ $money($order->contract_amount) }} with {{ $order->contractor->name }}." data-confirm-text="Approve" data-tone="primary">Approve</x-button>
                    @break
                @case('review')
                    <x-button icon="check-square" href="#measurements">Review Measurements</x-button>
                    @break
                @case('bill')
                    <x-button icon="file" data-remote-modal="{{ route('work-orders.bills.create', $order) }}" data-size="max-w-3xl">Bill</x-button>
                    @break
                @case('pay')
                    <x-button icon="tag" data-remote-modal="{{ route('contractor-payments.create', ['work_order' => $order->public_id, 'bill' => $nextBill->public_id]) }}" data-size="max-w-2xl">Pay {{ $nextBill->bill_no }}</x-button>
                    @break
                @case('settle')
                    <x-button icon="check-square" data-action="post" data-url="{{ route('work-orders.settle', $order) }}" data-reload data-confirm="Settle {{ $order->wo_no }}?" data-confirm-message="{{ $settleMessage }}" data-confirm-text="Settle" data-tone="primary">Settle</x-button>
                    @break
                @case('measure')
                    <x-button icon="plus" data-remote-modal="{{ route('work-orders.measurements.create', $order) }}" data-size="max-w-3xl">Measurement</x-button>
                    @break
            @endswitch
            @php
                $more = array_filter([
                    $s === 'draft' && Gate::allows('wo:edit', $order) ? ['Edit', 'edit', ['data-remote-modal' => route('work-orders.edit', $order), 'data-size' => 'max-w-3xl']] : null,
                    $s === 'approved' && $next !== 'measure' && Gate::allows('wo:measure', $order) && App\Support\Money::isPositive($remaining) ? ['Measurement', 'plus', ['data-remote-modal' => route('work-orders.measurements.create', $order), 'data-size' => 'max-w-3xl']] : null,
                    $s === 'approved' && $next !== 'bill' && Gate::allows('wo:bill', $order) && $hasApproved ? ['Bill', 'file', ['data-remote-modal' => route('work-orders.bills.create', $order), 'data-size' => 'max-w-3xl']] : null,
                    $s === 'approved' && Gate::allows('wo:pay', $order) ? ['Pay Advance', 'tag', ['data-remote-modal' => route('contractor-payments.create', ['work_order' => $order->public_id]), 'data-size' => 'max-w-2xl']] : null,
                    $s === 'approved' && Gate::allows('wo:edit', $order) && ! $order->variations->contains('status', 'pending') ? ['Variation', 'edit', ['data-remote-modal' => route('work-orders.variations.create', $order), 'data-size' => 'max-w-lg']] : null,
                    $s === 'approved' && $next !== 'settle' && Gate::allows('wo:bill', $order) ? ['Settle', 'check-square', ['data-action' => 'post', 'data-url' => route('work-orders.settle', $order), 'data-reload' => '', 'data-confirm' => 'Settle '.$order->wo_no.'?', 'data-confirm-message' => $settleMessage, 'data-confirm-text' => 'Settle', 'data-tone' => 'primary']] : null,
                    in_array($s, ['draft', 'approved'], true) && Gate::allows('wo:approve', $order) ? ['Cancel Work Order', 'x', ['data-remote-modal' => route('work-orders.cancel.form', $order), 'data-size' => 'max-w-md']] : null,
                ]);
            @endphp
            @if ($more)
                <div class="relative">
                    <x-button variant="secondary" data-menu-button aria-controls="wo-more" aria-expanded="false">More<x-icon name="chevron-down" size="h-4 w-4" /></x-button>
                    <div id="wo-more" data-menu hidden class="absolute right-0 z-30 mt-2 w-56 rounded-card border border-line bg-surface p-1.5 shadow-raised">
                        @foreach ($more as [$label, $icon, $attrs])
                            <button type="button" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm text-ink-700 hover:bg-surface-muted @if ($icon === 'x') text-danger-600 @endif" @foreach ($attrs as $k => $v) {{ $k }}="{{ $v }}" @endforeach><x-icon :name="$icon" size="h-4 w-4 text-ink-400" />{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <x-card>
                <div class="grid gap-5 sm:grid-cols-4">
                    <div class="sm:col-span-2"><p class="detail-label">Contractor</p><a href="{{ route('contractors.show', $order->contractor) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $order->contractor->name }}</a><p class="text-[13px] text-ink-500">{{ $order->title }}</p></div>
                    <div><p class="detail-label">Project</p><a href="{{ route('projects.show', $order->project) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $order->project->name }}</a><p class="text-[13px] text-ink-500">{{ $order->workPackage?->label() ?? $order->project->code }}</p></div>
                    <div><p class="detail-label">Status</p><x-status-badge :status="$s" /><p class="mt-1 text-[13px] text-ink-500">{{ App\Support\Format::date($order->start_date) }} – {{ App\Support\Format::date($order->end_date) }}</p></div>
                </div>
                @if ($order->scope)<p class="mt-4 whitespace-pre-line border-t border-line pt-4 text-sm text-ink-700">{{ $order->scope }}</p>@endif
            </x-card>

            <x-card>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3 lg:grid-cols-6">
                    <div><dt class="detail-label">Contract</dt><dd class="detail-value font-semibold"><x-money :value="$order->contract_amount" /></dd></div>
                    <div><dt class="detail-label">Work Done</dt><dd class="detail-value font-semibold"><x-money :value="$measured" /></dd><dd class="text-[12px] text-ink-500">{{ $pct }}%</dd>@if (App\Support\Money::isPositive($pendingMeasured))<dd class="whitespace-nowrap text-[12px] text-warning-600">+{{ $money($pendingMeasured) }} to review</dd>@endif</div>
                    <div><dt class="detail-label">Billed</dt><dd class="detail-value font-semibold"><x-money :value="$billed" /></dd></div>
                    <div><dt class="detail-label">Paid</dt><dd class="detail-value font-semibold"><x-money :value="$paid" /></dd></div>
                    <div><dt class="detail-label">Due Now</dt><dd @class(['detail-value text-lg font-semibold', 'text-danger-600' => App\Support\Money::isPositive($due), 'text-ink-900' => ! App\Support\Money::isPositive($due)])><x-money :value="$due" /></dd></div>
                    <div><dt class="detail-label">Retention</dt><dd class="detail-value font-semibold"><x-money :value="$retention" /></dd><dd class="text-[12px] text-ink-500">at settlement</dd></div>
                </dl>
                <x-progress :value="$pct" class="mt-5" :label="false" />
                <div class="mt-2 flex flex-wrap justify-between gap-2 text-[12px] text-ink-500">
                    <span>{{ $money($remaining) }} of the contract left to measure</span>
                    @if (App\Support\Money::isPositive($advance))
                        <span>{{ $money($advance) }} advance not yet adjusted
                            @can('wo:pay', $order)· <button type="button" class="font-medium text-accent-700 hover:underline" data-remote-modal="{{ route('contractor-payments.create', ['work_order' => $order->public_id, 'kind' => 'refund']) }}" data-size="max-w-2xl">Refund</button>@endcan
                        </span>
                    @endif
                </div>
            </x-card>

            @php
                $tabs = array_filter([
                    'measurements' => ['Measurements', $order->measurements->count()],
                    'bills' => ['Bills', $order->bills->whereNotIn('status', ['cancelled'])->count()],
                    'payments' => ['Payments', $order->payments->count()],
                    'variations' => $order->variations->isNotEmpty() ? ['Variations', $order->variations->count()] : null,
                    'files' => ['Files', $order->attachments_count],
                ]);
            @endphp
            <div class="flex gap-6 overflow-x-auto border-b border-line" role="tablist">
                @foreach ($tabs as $key => [$label, $count])
                    <button type="button" role="tab" class="tab" id="tab-{{ $key }}" data-tab="{{ $key }}" aria-controls="panel-{{ $key }}">{{ $label }}<span class="ml-1.5 rounded-full bg-ink-900/5 px-1.5 text-[12px] font-medium text-ink-500">{{ $count }}</span>@if ($key === 'measurements' && $hasDraft)<span class="ml-1 h-1.5 w-1.5 rounded-full bg-warning-600" data-tooltip="Awaiting approval"></span>@endif</button>
                @endforeach
            </div>

            <section id="panel-measurements" role="tabpanel" aria-labelledby="tab-measurements">
                <x-card :padded="false">
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
            </section>
            <section id="panel-bills" role="tabpanel" aria-labelledby="tab-bills" hidden>
                <x-data-table :url="route('contractor-bills.table', ['work_order' => $order->public_id])" sort="date" dir="desc" :search="false" :columns="[
                    ['label' => 'Bill', 'sort' => 'date'], ['label' => 'Gross', 'class' => 'text-right'], ['label' => 'Net Payable', 'class' => 'text-right'], ['label' => 'Outstanding', 'class' => 'text-right'], ['label' => 'Status'], ['label' => '', 'class' => 'w-24'],
                ]" />
            </section>
            <section id="panel-payments" role="tabpanel" aria-labelledby="tab-payments" hidden>
                <x-data-table :url="route('contractor-payments.table', ['work_order' => $order->public_id, 'contractor' => $order->contractor->public_id])" sort="date" dir="desc" :search="false" :columns="[
                    ['label' => 'Payment', 'sort' => 'date'], ['label' => 'Against'], ['label' => 'Method'], ['label' => 'Amount', 'class' => 'text-right', 'sort' => 'amount'], ['label' => 'Status'],
                ]" />
            </section>
            @isset($tabs['variations'])
                <section id="panel-variations" role="tabpanel" aria-labelledby="tab-variations" hidden>
                
                <x-card :padded="false">
                    <table class="data-table">
                        <thead><tr><th>Variation</th><th class="text-right">From</th><th class="text-right">To</th><th class="text-right">Change</th><th>Status</th><th class="w-24"></th></tr></thead>
                        <tbody>
                            @foreach ($order->variations->sortByDesc('id') as $v)
                                <tr>
                                    <td class="min-w-48"><p class="font-medium text-ink-900">{{ $v->variation_no }}</p><p class="text-[13px] text-ink-500">{{ $v->reason }}</p>@if ($v->rejection_reason)<p class="text-[13px] text-danger-600">{{ $v->rejection_reason }}</p>@endif</td>
                                    <td class="text-right"><x-money :value="$v->old_amount" muted /></td>
                                    <td class="text-right"><x-money :value="$v->new_amount" /></td>
                                    <td class="whitespace-nowrap text-right font-medium {{ App\Support\Money::isPositive($v->change()) ? 'text-warning-600' : 'text-success-600' }}">{{ App\Support\Money::isPositive($v->change()) ? '+' : '' }}{{ App\Support\Format::money($v->change()) }}</td>
                                    <td><x-status-badge :status="$v->status" /></td>
                                    <td class="whitespace-nowrap text-right">
                                        @if ($v->status === 'pending' && Gate::allows('wo:approve', $order))
                                            <button type="button" class="btn btn-ghost btn-icon text-success-600" data-action="post" data-url="{{ route('work-orders.variations.approve', [$order, $v]) }}" data-reload data-confirm="Approve {{ $v->variation_no }}?" data-confirm-message="Contract changes to {{ App\Support\Format::money($v->new_amount) }}." data-confirm-text="Approve" data-tone="primary" aria-label="Approve variation"><x-icon name="check" size="h-4 w-4" /></button>
                                            <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-remote-modal="{{ route('work-orders.variations.reject.form', [$order, $v]) }}" data-size="max-w-md" aria-label="Reject variation"><x-icon name="x" size="h-4 w-4" /></button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            
                </section>
            @endisset
            <section id="panel-files" role="tabpanel" aria-labelledby="tab-files" hidden>
                <x-attachments :owner="$order" type="work-order" />
            </section>
        </div>

        <x-card title="History" :padded="false"><x-timeline :items="$history" /></x-card>
    </div>
</x-layouts.app>
