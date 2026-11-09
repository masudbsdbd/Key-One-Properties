@forelse ($bookings as $b)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('bookings.show', $b) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $b->booking_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($b->booking_date) }}</p></td>
        <td class="text-ink-700">{{ $b->project->code }} · {{ $b->flat->building->code }}<span class="block font-medium text-ink-900">Unit {{ $b->flat->flat_no }}</span></td>
        <td class="text-ink-700">{{ $b->customer->full_name }}<span class="block text-[13px] text-ink-500">{{ $b->customer->customer_code }}</span></td>
        <td class="text-right font-medium"><x-money :value="$b->agreed_price" /></td>
        <td class="text-right"><x-money :value="$b->booking_amount_expected" muted /></td>
        <td><x-status-badge :status="$b->status" :labels="App\Models\FlatBooking::STATUSES" /></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="calendar-check" title="No bookings" /></td></tr>
@endforelse
