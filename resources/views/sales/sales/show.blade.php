@php
    $user = auth()->user();
    $s = $sale->status;
    $active = in_array($s, App\Models\FlatSale::ACTIVE, true);
    $pendingCancel = $sale->cancellations->firstWhere('status', 'requested');
    $steps = ['approved' => 'Approved', 'registration_pending' => 'Registration', 'registered' => 'Registered', 'handover_ready' => 'Handover Ready', 'handed_over' => 'Handed Over'];
    $reached = array_search($s, array_keys($steps), true);
@endphp
<x-layouts.app :title="$sale->sale_no">
    <x-page-header :title="$sale->sale_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Sales' => route('sales.index'), $sale->sale_no => null]">
        <x-slot:actions>
            <x-document-menu :url="route('sales.document', $sale)" />
            @if (in_array($s, ['draft', 'rejected'], true))
                @can('sales.sale.create')
                    <x-button icon="arrow-right" data-action="post" data-url="{{ route('sales.submit', $sale) }}" data-reload data-confirm="Submit {{ $sale->sale_no }} for approval?" data-confirm-text="Submit" data-tone="primary">Submit</x-button>
                    <x-button variant="secondary" icon="edit" data-remote-modal="{{ route('sales.edit', $sale) }}" data-size="max-w-3xl">Edit</x-button>
                @endcan
            @endif
            @if ($s === 'pending')
                @can('sales.sale.approve')
                    <x-button icon="check" data-action="post" data-url="{{ route('sales.approve', $sale) }}" data-reload data-confirm="Approve {{ $sale->sale_no }}?" data-confirm-message="Unit {{ $sale->flat->flat_no }} will be marked sold and {{ App\Support\Format::money($sale->net_contract_value) }} charged to {{ $sale->customer->full_name }}." data-confirm-text="Approve" data-tone="primary">Approve</x-button>
                    <x-button variant="ghost" data-remote-modal="{{ route('sales.reject.form', $sale) }}" data-size="max-w-md">Reject</x-button>
                @endcan
            @endif
            @if ($active)
                @can('collections.payment.collect')@if (Route::has('customer-payments.create'))<x-button icon="tag" data-remote-modal="{{ route('customer-payments.create', ['sale' => $sale->public_id]) }}" data-size="max-w-2xl">Receive Payment</x-button>@endif @endcan
                @can('sales.booking.cancel.request')
                    @if (in_array($s, ['approved', 'registration_pending'], true))<x-button variant="ghost" icon="x" data-remote-modal="{{ route('sales.cancel.form', $sale) }}" data-size="max-w-md" :disabled="(bool) $pendingCancel" data-disabled-reason="A cancellation is waiting for approval" aria-label="Cancel sale" />@endif
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($pendingCancel)
        <div class="mb-6 rounded-card border border-warning-600/30 bg-warning-50 px-5 py-3 text-sm text-warning-600">Cancellation {{ $pendingCancel->cancellation_no }} is waiting for approval · {{ $pendingCancel->reason }}</div>
    @endif

    @if ($active)
        <x-card class="mb-6">
            <ol class="grid gap-3 sm:grid-cols-5">
                @foreach ($steps as $key => $label)
                    @php($done = $reached !== false && $loop->index <= $reached)
                    <li class="flex items-center gap-2 text-sm {{ $done ? 'font-medium text-ink-900' : 'text-ink-500' }}">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full {{ $done ? 'bg-accent-500 text-white' : 'border border-line' }}">@if ($done)<x-icon name="check" size="h-3.5 w-3.5" />@else{{ $loop->iteration }}@endif</span>{{ $label }}
                    </li>
                @endforeach
            </ol>
        </x-card>
    @endif

    <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
        <button type="button" role="tab" class="tab" id="tab-overview" data-tab="overview" aria-controls="panel-overview">Overview</button>
        @if ($sale->agreement)<button type="button" role="tab" class="tab" id="tab-agreement" data-tab="agreement" aria-controls="panel-agreement">Agreement & Handover</button>@endif
        <button type="button" role="tab" class="tab" id="tab-payments" data-tab="payments" aria-controls="panel-payments">Payments</button>
        <button type="button" role="tab" class="tab" id="tab-files" data-tab="files" aria-controls="panel-files">Files</button>
        <button type="button" role="tab" class="tab" id="tab-history" data-tab="history" aria-controls="panel-history">History</button>
    </div>

    <section id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" class="grid gap-6 xl:grid-cols-3">
        <x-card class="min-w-0 xl:col-span-2">
            <div class="grid gap-5 sm:grid-cols-2">
                <div><p class="detail-label">Unit</p><p class="detail-value font-semibold"><x-ref :to="$sale->project">{{ $sale->project->name }}</x-ref> · <x-ref :to="$sale->flat">Unit {{ $sale->flat->flat_no }}</x-ref></p><p class="text-[13px] text-ink-500">{{ $sale->flat->building->name }} · {{ $sale->flat->floor->label }} · {{ number_format((float) $sale->flat->saleable_area) }} {{ $sale->flat->area_unit }}</p></div>
                <div><p class="detail-label">Customer</p><a href="{{ route('customers.show', $sale->customer) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $sale->customer->full_name }}</a><p class="text-[13px] text-ink-500">{{ $sale->customer->customer_code }} · {{ App\Support\Phone::display($sale->customer->primary_mobile) }}</p></div>
                <div><p class="detail-label">Agreement Date</p><p class="detail-value">{{ App\Support\Format::date($sale->agreement_date) }}</p></div>
                <div><p class="detail-label">Booking</p>@if ($sale->booking)<a href="{{ route('bookings.show', $sale->booking) }}" class="detail-value text-accent-700 hover:underline">{{ $sale->booking->booking_no }}</a>@else<p class="detail-value">Direct sale</p>@endif</div>
                <div><p class="detail-label">Status</p><x-status-badge :status="$s" :labels="App\Models\FlatSale::STATUSES" /></div>
                <div><p class="detail-label">Approved By</p><p class="detail-value">{{ $sale->approver?->name ?? '—' }}</p></div>
            </div>
            @if ($sale->status_reason)<p class="mt-4 border-t border-line pt-4 text-sm text-danger-600">{{ $sale->status_reason }}</p>@endif
            @if ($sale->notes)<p class="mt-4 whitespace-pre-line border-t border-line pt-4 text-sm text-ink-700">{{ $sale->notes }}</p>@endif
        </x-card>
        <x-card title="Contract">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-ink-500">Sale Price</dt><dd><x-money :value="$sale->sale_price" /></dd></div>
                <div class="flex justify-between"><dt class="text-ink-500">Discount</dt><dd><x-money :value="'-'.$sale->discount_amount" muted /></dd></div>
                <div class="flex justify-between border-t border-line pt-2 font-semibold text-ink-900"><dt>Contract Value</dt><dd><x-money :value="$sale->net_contract_value" /></dd></div>
                <div class="flex justify-between"><dt class="text-ink-500">Received</dt><dd><x-money :value="$refundable" /></dd></div>
                @if ($balance !== null)<div class="flex justify-between"><dt class="text-ink-500">Customer Balance</dt><dd class="font-medium"><x-money :value="$balance" /></dd></div>@endif
            </dl>
        </x-card>
    </section>

    @if ($sale->agreement)
        <section id="panel-agreement" role="tabpanel" aria-labelledby="tab-agreement" hidden class="grid gap-6 lg:grid-cols-3">
            @foreach (['agreement' => ['Agreement', $sale->agreement->agreement_no, App\Models\SaleAgreement::STATUSES[$sale->agreement->status], $sale->agreement->signed_at ? 'Signed '.App\Support\Format::date($sale->agreement->signed_at) : 'Not signed'],
                'registration' => ['Registration', $sale->registration->registration_no ?? '—', App\Models\FlatRegistration::STATUSES[$sale->registration->status], $sale->registration->registered_at ? 'Registered '.App\Support\Format::date($sale->registration->registered_at) : ''],
                'handover' => ['Handover', count($sale->handover->checklist_json ?? []).' of '.count(App\Models\FlatHandover::CHECKLIST).' checks', App\Models\FlatHandover::STATUSES[$sale->handover->status], $sale->handover->completed_at ? 'Handed over '.App\Support\Format::date($sale->handover->completed_at) : ($sale->handover->scheduled_at ? 'Scheduled '.App\Support\Format::date($sale->handover->scheduled_at) : '')]] as $stage => [$title, $line, $status, $sub])
                <x-card :title="$title">
                    <x-slot:actions>
                        @can('sales.sale.'.$stage.'.manage')
                            @if ($active)<x-button size="sm" variant="secondary" icon="edit" data-remote-modal="{{ route('sales.stage.form', [$sale, $stage]) }}" data-size="max-w-xl">Update</x-button>@endif
                        @endcan
                    </x-slot:actions>
                    <p class="font-semibold text-ink-900">{{ $line }}</p>
                    <p class="mt-1 text-sm text-ink-500">{{ $status }}@if ($sub) · {{ $sub }}@endif</p>
                </x-card>
            @endforeach
        </section>
    @endif

    <section id="panel-payments" role="tabpanel" aria-labelledby="tab-payments" hidden>
        <x-card :padded="false">
            @forelse ($sale->payments as $p)
                <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-0">
                    <span class="min-w-0 flex-1"><x-ref :to="$p" class="block font-medium text-ink-900">{{ $p->payment_no }}</x-ref><span class="block text-[13px] text-ink-500">{{ App\Support\Format::date($p->payment_date) }} · {{ App\Models\CustomerPayment::METHODS[$p->method] ?? $p->method }}</span></span>
                    <x-money :value="$p->amount" class="font-medium" />
                    <x-status-badge :status="$p->status" />
                </div>
            @empty
                <x-empty-state icon="tag" title="No payments" />
            @endforelse
        </x-card>
    </section>

    <section id="panel-files" role="tabpanel" aria-labelledby="tab-files" hidden>
        <x-attachments :owner="$sale" type="sale" title="Agreement, Registration & Handover Files" />
    </section>

    <section id="panel-history" role="tabpanel" aria-labelledby="tab-history" hidden>
        <x-card :padded="false">@include('projects.partials._activity', ['entries' => $activity])</x-card>
    </section>
</x-layouts.app>
