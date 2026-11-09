@php
    $user = auth()->user();
    $s = $refund->status;
    $history = collect([
        ['title' => 'Requested', 'meta' => $refund->requester->name.' · '.App\Support\Format::datetime($refund->created_at), 'body' => $refund->reason, 'tone' => 'info'],
        $refund->approved_at && $s !== 'rejected' ? ['title' => 'Approved', 'meta' => $refund->approver?->name.' · '.App\Support\Format::datetime($refund->approved_at), 'tone' => 'success'] : null,
        $s === 'rejected' ? ['title' => 'Rejected', 'meta' => App\Support\Format::datetime($refund->approved_at), 'body' => $refund->status_reason, 'tone' => 'danger'] : null,
        $refund->paid_at ? ['title' => 'Paid', 'meta' => App\Support\Format::date($refund->paid_on).' · '.App\Models\CustomerPayment::METHODS[$refund->payment_method], 'body' => $refund->payment_reference, 'tone' => 'success'] : null,
        $refund->reversed_at ? ['title' => 'Reversed', 'meta' => App\Support\Format::datetime($refund->reversed_at), 'body' => $refund->status_reason, 'tone' => 'danger'] : null,
    ])->filter()->values()->all();
@endphp
<x-layouts.app :title="$refund->refund_no">
    <x-page-header :title="$refund->refund_no" :breadcrumbs="['Dashboard' => route('dashboard'), 'Refunds' => route('refunds.index'), $refund->refund_no => null]">
        <x-slot:actions>
            <x-document-menu :url="route('refunds.document', $refund)" />
            @if ($s === 'pending')
                @can('sales.refund.approve')
                    <x-button icon="check" data-remote-modal="{{ route('refunds.approve.form', $refund) }}" data-size="max-w-md">Approve</x-button>
                    <x-button variant="ghost" data-remote-modal="{{ route('refunds.reason', [$refund, 'reject']) }}" data-size="max-w-md">Reject</x-button>
                @endcan
            @endif
            @if ($s === 'approved')
                @can('sales.refund.pay')<x-button icon="tag" data-remote-modal="{{ route('refunds.pay.form', $refund) }}" data-size="max-w-lg">Pay</x-button>@endcan
            @endif
            @if ($s === 'paid')
                @can('sales.refund.reverse')<x-button variant="ghost" icon="restore" data-remote-modal="{{ route('refunds.reason', [$refund, 'reverse']) }}" data-size="max-w-md">Reverse</x-button>@endcan
            @endif
        </x-slot:actions>
    </x-page-header>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <x-card>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><p class="detail-label">Customer</p><a href="{{ route('customers.show', $refund->customer) }}" class="detail-value font-semibold text-ink-900 hover:text-accent-700">{{ $refund->customer->full_name }}</a><p class="text-[13px] text-ink-500">{{ $refund->customer->customer_code }}</p></div>
                    <div><p class="detail-label">{{ $refund->net_amount !== null ? 'Refund' : 'Requested' }}</p><p class="text-xl font-semibold text-ink-900"><x-money :value="$refund->payable()" /></p><x-status-badge :status="$s" />
                        @if ((float) $refund->deduction_amount)<p class="mt-1 text-[13px] text-ink-500">Requested <x-money :value="$refund->amount" /> · {{ rtrim(rtrim((string) $refund->deduction_percent, '0'), '.') }}% deduction <x-money :value="$refund->deduction_amount" /></p>@endif
                    </div>
                    <div><p class="detail-label">Against</p>
                        @if ($refund->sale)<a href="{{ route('sales.show', $refund->sale) }}" class="detail-value text-accent-700 hover:underline">{{ $refund->sale->sale_no }}</a>@elseif ($refund->booking)<a href="{{ route('bookings.show', $refund->booking) }}" class="detail-value text-accent-700 hover:underline">{{ $refund->booking->booking_no }}</a>@endif
                        <p class="text-[13px] text-ink-500"><x-ref :to="$refund->project">{{ $refund->project->name }}</x-ref></p>
                    </div>
                    <div><p class="detail-label">Cancellation</p><p class="detail-value">{{ $refund->cancellation?->cancellation_no ?? '—' }}</p></div>
                </div>
            </x-card>
            <x-attachments :owner="$refund" type="refund" />
        </div>
        <x-card title="History" :padded="false"><x-timeline :items="$history" /></x-card>
    </div>
</x-layouts.app>
