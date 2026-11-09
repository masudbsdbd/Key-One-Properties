@forelse ($refunds as $r)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('refunds.show', $r) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $r->refund_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($r->created_at) }}</p></td>
        <td class="text-ink-700">{{ $r->customer->full_name }}</td>
        <td class="text-ink-700">{{ $r->sale?->sale_no ?? $r->booking?->booking_no }}<span class="block text-[13px] text-ink-500">{{ $r->project->code }}</span></td>
        <td class="text-right font-medium"><x-money :value="$r->payable()" /></td>
        <td><x-status-badge :status="$r->status" /></td>
    </tr>
@empty
    <tr><td colspan="5"><x-empty-state icon="restore" title="No refunds" /></td></tr>
@endforelse
