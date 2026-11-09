@php($canDecide = auth()->user()->can('sales.price.change.approve'))
@forelse ($revisions as $r)
    <tr>
        <td><a href="{{ route('flats.show', $r->flat) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $r->flat->project->code }} · {{ $r->flat->building->code }} · Unit {{ $r->flat->flat_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($r->created_at) }} · {{ $r->requester->name }}</p></td>
        <td class="text-right"><x-money :value="$r->old_price" muted /></td>
        <td class="text-right font-medium"><x-money :value="$r->new_price" /></td>
        <td class="text-ink-700">{{ $r->reason }}@if ($r->rejection_reason)<span class="block text-[13px] text-danger-600">{{ $r->rejection_reason }}</span>@endif</td>
        <td><x-status-badge :status="$r->status" /></td>
        <td class="whitespace-nowrap text-right">
            @if ($r->status === 'pending' && $canDecide)
                <button type="button" class="btn btn-ghost btn-icon text-success-600" data-row-action="post" data-url="{{ route('price-revisions.approve', $r) }}" data-confirm="Change the price of unit {{ $r->flat->flat_no }}?" data-confirm-message="{{ App\Support\Format::money($r->old_price) }} → {{ App\Support\Format::money($r->new_price) }}. Existing bookings and sales keep their price." data-confirm-text="Approve" aria-label="Approve price change"><x-icon name="check" size="h-4 w-4" /></button>
                <button type="button" class="btn btn-ghost btn-icon text-danger-600" data-remote-modal="{{ route('price-revisions.reject.form', $r) }}" data-size="max-w-md" aria-label="Reject price change"><x-icon name="x" size="h-4 w-4" /></button>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="tag" title="No price changes" /></td></tr>
@endforelse
