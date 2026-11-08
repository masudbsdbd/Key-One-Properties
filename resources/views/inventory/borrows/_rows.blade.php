@forelse ($borrows as $borrow)
    @php($outstanding = $borrow->lines->reduce(fn ($c, $l) => App\Support\Money::add($c, $l->outstanding()), '0'))
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('borrows.show', $borrow) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $borrow->borrow_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($borrow->created_at) }} · {{ $borrow->lines->count() }} {{ Str::plural('line', $borrow->lines->count()) }}</p></td>
        <td class="text-ink-700">{{ $borrow->sourceProject->name }}</td>
        <td class="text-ink-700">{{ $borrow->destinationProject->name }}</td>
        <td class="tabular-nums">@if (App\Support\Money::isPositive($outstanding))<x-badge tone="warning">{{ rtrim(rtrim($outstanding, '0'), '.') }} units</x-badge>@else<span class="text-ink-400">—</span>@endif</td>
        <td><x-status-badge :status="$borrow->status" /></td>
    </tr>
@empty
    <tr><td colspan="5"><x-empty-state icon="refresh" title="No borrows" /></td></tr>
@endforelse
