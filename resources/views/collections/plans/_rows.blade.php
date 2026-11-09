@forelse ($plans as $p)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('installment-plans.show', $p) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $p->plan_no }}</a><p class="text-[13px] text-ink-500">{{ App\Support\Format::date($p->start_date) }} · {{ App\Models\InstallmentPlan::FREQUENCIES[$p->installment_frequency] }}</p></td>
        <td class="text-ink-700">{{ $p->sale->sale_no }}<span class="block text-[13px] text-ink-500">{{ $p->sale->project->code }} · Unit {{ $p->sale->flat->flat_no }}</span></td>
        <td class="text-ink-700">{{ $p->customer->full_name }}</td>
        <td class="text-right font-medium"><x-money :value="$p->principal_amount" /></td>
        <td class="text-right tabular-nums">{{ $p->items_count }}</td>
        <td><x-status-badge :status="$p->status" :labels="App\Models\InstallmentPlan::STATUSES" /></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="calendar" title="No installment plans" /></td></tr>
@endforelse
