@forelse ($calcs as $c)
    <tr>
        <td class="whitespace-nowrap"><a href="{{ route('profit-calculations.show', $c) }}" class="font-medium text-ink-900 hover:text-accent-700">{{ $c->calculation_no }}</a><p class="text-[13px] text-ink-500">v{{ $c->version }} · {{ App\Support\Format::date($c->created_at) }}</p></td>
        <td class="text-ink-700"><x-ref :to="$c->project">{{ $c->project->code }}</x-ref> · {{ $c->flat->building->code }}<x-ref :to="$c->flat" class="block font-medium text-ink-900">Unit {{ $c->flat->flat_no }}</x-ref></td>
        <td class="text-right"><x-money :value="$c->revenue_amount" /></td>
        <td class="text-right"><x-money :value="$c->totalCost()" muted /></td>
        <td @class(['text-right font-semibold', 'text-danger-600' => (float) $c->net_profit_amount < 0])><x-money :value="$c->net_profit_amount" /></td>
        <td class="text-right tabular-nums">{{ App\Modules\Investors\Controllers\AgreementController::pct($c->investor_share_percent) }}</td>
        <td><x-status-badge :status="$c->status" :labels="App\Models\FlatProfitCalculation::STATUSES" />@if (! $c->revenue_eligible && in_array($c->status, ['draft', 'submitted'], true)) <x-badge tone="warning">Provisional</x-badge>@endif</td>
    </tr>
@empty
    <tr><td colspan="7"><x-empty-state icon="bar-chart" title="No calculations" /></td></tr>
@endforelse
