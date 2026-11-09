@forelse ($orders as $order)
    @php($pct = (float) $order->contract_amount > 0 ? min(100, round((float) ($order->measured ?? 0) / (float) $order->contract_amount * 100)) : 0)
    <tr>
        <td class="min-w-56"><a href="{{ route('work-orders.show', $order) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $order->wo_no }}</a><p class="truncate text-[13px] text-ink-500">{{ $order->title }}</p></td>
        @if ($showContractor)<td class="text-ink-700"><x-ref :to="$order->contractor">{{ $order->contractor->name }}</x-ref></td>@endif
        <td class="text-ink-700"><x-ref :to="$order->project">{{ $order->project->code }}</x-ref><span class="block text-[13px] text-ink-400">{{ $order->project->name }}</span></td>
        <td class="text-right"><x-money :value="$order->contract_amount" /></td>
        <td class="min-w-32"><x-progress :value="$pct" size="h-1.5" /></td>
        <td><x-status-badge :status="$order->status" /></td>
    </tr>
@empty
    <tr><td colspan="{{ $showContractor ? 6 : 5 }}"><x-empty-state icon="check-square" title="No work orders" /></td></tr>
@endforelse
