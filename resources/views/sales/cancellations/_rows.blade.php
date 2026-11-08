@php($canDecide = auth()->user()->can('sales.booking.cancel.approve'))
@forelse ($cancellations as $c)
    @php($doc = $c->booking ?? $c->sale)
    <tr>
        <td class="min-w-56"><p class="font-medium text-ink-900">{{ $c->cancellation_no }}</p><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($c->requested_at) }} · {{ $c->requester->name }} · {{ $c->reason }}</p>@if ($c->rejection_reason)<p class="text-[13px] text-danger-600">{{ $c->rejection_reason }}</p>@endif</td>
        <td><a href="{{ $c->booking ? route('bookings.show', $c->booking) : route('sales.show', $c->sale) }}" class="text-accent-700 hover:underline">{{ $doc->booking_no ?? $doc->sale_no }}</a><span class="block text-[13px] text-ink-500">Unit {{ $doc->flat->flat_no }}</span></td>
        <td class="text-ink-700">{{ $doc->customer->full_name }}</td>
        <td class="text-right"><x-money :value="$c->refund_expected" /></td>
        <td><x-status-badge :status="$c->status" /></td>
        <td class="whitespace-nowrap text-right">
            @if ($c->status === 'requested' && $canDecide)
                <button type="button" class="btn btn-ghost btn-icon text-success-600" data-row-action="post" data-url="{{ route('cancellations.approve', $c) }}" data-confirm="Approve {{ $c->cancellation_no }}?" data-confirm-message="Unit {{ $doc->flat->flat_no }} becomes available again.{{ (float) $c->refund_expected ? ' A refund of '.App\Support\Format::money($c->refund_expected).' will wait for approval.' : '' }}" data-confirm-text="Approve" aria-label="Approve cancellation"><x-icon name="check" size="h-4 w-4" /></button>
                <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-remote-modal="{{ route('cancellations.reject.form', $c) }}" data-size="max-w-md" aria-label="Reject cancellation"><x-icon name="x" size="h-4 w-4" /></button>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="x" title="No cancellations" /></td></tr>
@endforelse
