@php
    $user = auth()->user();
    $s = $plan->status;
    $items = $plan->items;
    $paid = App\Support\Money::add('0', ...$items->map(fn ($i) => App\Support\Money::add($i->principal_paid, $i->fee_paid))->all());
    $outstanding = App\Support\Money::add('0', ...$items->map(fn ($i) => $i->outstanding())->all());
    $pendingReschedule = $plan->reschedules->firstWhere('status', 'requested');
@endphp
<x-layouts.app :title="$plan->plan_no">
    <x-page-header :title="$plan->plan_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Installment Plans' => route('installment-plans.index'), $plan->plan_no => null]">
        <x-slot:actions>
            <x-document-menu :url="route('installment-plans.document', $plan)" />
            @if (in_array($s, ['draft', 'rejected'], true))
                @can('collections.plan.create')
                    <x-button icon="arrow-right" data-action="post" data-url="{{ route('installment-plans.decide', [$plan, 'submit']) }}" data-reload data-confirm="Submit {{ $plan->plan_no }}?" data-confirm-text="Submit" data-tone="primary">Submit</x-button>
                    <x-button variant="ghost" icon="trash" data-action="post" data-url="{{ route('installment-plans.decide', [$plan, 'discard']) }}" data-reload data-confirm="Discard {{ $plan->plan_no }}?" data-confirm-text="Discard" aria-label="Discard plan" />
                @endcan
            @endif
            @if ($s === 'pending')
                @can('collections.plan.approve')
                    <x-button icon="check" data-action="post" data-url="{{ route('installment-plans.decide', [$plan, 'approve']) }}" data-reload data-confirm="Activate {{ $plan->plan_no }}?" data-confirm-message="{{ $items->count() }} due dates for {{ $plan->customer->full_name }}. Any advance already paid is applied." data-confirm-text="Approve" data-tone="primary">Approve</x-button>
                    <x-button variant="ghost" data-action="post" data-url="{{ route('installment-plans.decide', [$plan, 'reject']) }}" data-reload data-confirm="Reject {{ $plan->plan_no }}?" data-confirm-text="Reject">Reject</x-button>
                @endcan
            @endif
            @if ($s === 'active')
                @can('collections.payment.collect')<x-button icon="tag" data-remote-modal="{{ route('customer-payments.create', ['sale' => $plan->sale->public_id]) }}" data-size="max-w-2xl">Receive Payment</x-button>@endcan
                @can('collections.plan.reschedule.request')<x-button variant="secondary" icon="calendar" data-remote-modal="{{ route('installment-plans.reschedule.form', $plan) }}" data-size="max-w-2xl" :disabled="(bool) $pendingReschedule" data-disabled-reason="A reschedule is waiting for approval">Reschedule</x-button>@endcan
                @can('collections.latefee.manage')<x-button variant="ghost" icon="alert" data-action="post" data-url="{{ route('installment-plans.late-fees', $plan) }}" data-reload data-confirm="Charge late fees now?" data-confirm-message="{{ $policy && $policy->fee_type !== 'none' ? App\Models\CustomerLateFeePolicy::TYPES[$policy->fee_type].' · '.rtrim(rtrim($policy->fee_value, '0'), '.').($policy->fee_type === 'percent' ? '%' : '').' · '.$policy->grace_days.' grace days' : 'No late-fee policy for this customer.' }}" data-confirm-text="Charge" data-tone="primary" :disabled="! $policy || $policy->fee_type === 'none'" data-disabled-reason="No late-fee policy for this customer">Late Fees</x-button>@endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Contract Value" :value="App\Support\Format::money($plan->contract_value, false)" icon="door" tone="accent" />
        <x-stat-card label="Paid on Schedule" :value="App\Support\Format::money($paid, false)" icon="check" tone="success" />
        <x-stat-card label="Outstanding" :value="App\Support\Format::money($outstanding, false)" icon="clock" tone="warning" />
        <x-stat-card label="Unapplied Advance" :value="App\Support\Format::money($advance, false)" icon="tag" tone="info" />
    </div>

    @if ($pendingReschedule)
        <x-card class="mb-6" :title="'Reschedule '.$pendingReschedule->reschedule_no">
            <x-slot:actions>
                @can('collections.plan.reschedule.approve')
                    <x-button size="sm" icon="check" data-action="post" data-url="{{ route('installment-plans.reschedules.decide', [$plan, $pendingReschedule, 'approve']) }}" data-reload data-confirm="Apply the new schedule?" data-confirm-text="Apply" data-tone="primary">Approve</x-button>
                    <x-button size="sm" variant="ghost" data-remote-modal="{{ route('installment-plans.reschedules.reject.form', [$plan, $pendingReschedule]) }}" data-size="max-w-md">Reject</x-button>
                @endcan
            </x-slot:actions>
            <div class="grid gap-6 md:grid-cols-2">
                <div><p class="detail-label mb-2">Current</p>@foreach ($pendingReschedule->old_schedule_snapshot as $o)<p class="flex justify-between text-sm"><span>#{{ $o['sequence_no'] }} · {{ App\Support\Format::date(\Illuminate\Support\Carbon::parse($o['due_date'])) }}</span><x-money :value="$o['principal_outstanding']" /></p>@endforeach</div>
                <div><p class="detail-label mb-2">Proposed</p>@foreach ($pendingReschedule->new_schedule_snapshot as $n)<p class="flex justify-between text-sm"><span>{{ App\Support\Format::date(\Illuminate\Support\Carbon::parse($n['due_date'])) }}</span><x-money :value="$n['amount']" /></p>@endforeach</div>
            </div>
            <p class="mt-3 text-sm text-ink-500">{{ $pendingReschedule->reason }} · {{ $pendingReschedule->requester->name }}</p>
        </x-card>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Schedule" :padded="false" class="min-w-0 xl:col-span-2">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Due</th><th class="text-right">Principal</th><th class="text-right">Late Fee</th><th class="text-right">Paid</th><th class="text-right">Outstanding</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($items as $i)
                            <tr @class(['opacity-60' => $i->status === 'rescheduled'])>
                                <td class="text-ink-500">{{ $i->sequence_no }}</td>
                                <td class="whitespace-nowrap">{{ App\Support\Format::date($i->due_date) }}@if ($i->kind === 'down_payment')<span class="block text-[13px] text-ink-500">Down payment</span>@endif</td>
                                <td class="text-right"><x-money :value="$i->principal_due" /></td>
                                <td class="text-right">
                                    @foreach ($i->assessments as $a)
                                        <span class="block whitespace-nowrap {{ $a->status === 'waived' ? 'text-ink-400 line-through' : 'text-warning-600' }}"><x-money :value="$a->amount" />
                                            @if ($a->status === 'posted')@can('collections.latefee.waive')<button type="button" class="btn btn-ghost btn-icon h-6 w-6" data-remote-modal="{{ route('late-fees.waive.form', $a) }}" data-size="max-w-md" aria-label="Waive late fee"><x-icon name="x" size="h-3.5 w-3.5" /></button>@endcan @endif
                                        </span>
                                    @endforeach
                                </td>
                                <td class="text-right"><x-money :value="App\Support\Money::add($i->principal_paid, $i->fee_paid)" muted /></td>
                                <td class="text-right font-medium"><x-money :value="$i->outstanding()" /></td>
                                <td><x-status-badge :status="$i->status" :labels="App\Models\InstallmentScheduleItem::STATUSES" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
        <x-card title="Plan">
            <dl class="space-y-3 text-sm">
                <div><dt class="detail-label">Customer</dt><dd><a href="{{ route('customers.statement', $plan->customer) }}" class="font-medium text-accent-700 hover:underline">{{ $plan->customer->full_name }}</a></dd></div>
                <div><dt class="detail-label">Sale</dt><dd><a href="{{ route('sales.show', $plan->sale) }}" class="text-accent-700 hover:underline">{{ $plan->sale->sale_no }}</a> · {{ $plan->sale->project->code }} · Unit {{ $plan->sale->flat->flat_no }}</dd></div>
                <div class="flex justify-between border-t border-line pt-3"><dt class="text-ink-500">Contract Value</dt><dd><x-money :value="$plan->contract_value" /></dd></div>
                <div class="flex justify-between"><dt class="text-ink-500">Booking Money</dt><dd><x-money :value="'-'.$plan->booking_credit" /></dd></div>
                <div class="flex justify-between font-semibold"><dt>Scheduled</dt><dd><x-money :value="$plan->principal_amount" /></dd></div>
                <div class="border-t border-line pt-3"><dt class="detail-label">Late-Fee Policy</dt><dd>@if ($policy && $policy->fee_type !== 'none'){{ App\Models\CustomerLateFeePolicy::TYPES[$policy->fee_type] }} · {{ rtrim(rtrim($policy->fee_value, '0'), '.') }}{{ $policy->fee_type === 'percent' ? '%' : '' }} · {{ App\Models\CustomerLateFeePolicy::FREQUENCIES[$policy->frequency] }}@else None @endif</dd></div>
                <div><dt class="detail-label">Status</dt><dd><x-status-badge :status="$s" :labels="App\Models\InstallmentPlan::STATUSES" /></dd></div>
                <div class="text-[13px] text-ink-500">{{ $plan->creator->name }}@if ($plan->approver) · approved by {{ $plan->approver->name }}@endif</div>
            </dl>
        </x-card>
    </div>
</x-layouts.app>
