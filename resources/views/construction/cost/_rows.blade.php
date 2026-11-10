@forelse ($rows as $row)
    <tr>
        <td class="whitespace-nowrap">{{ App\Support\Format::date($row->effective_at) }}</td>
        <td class="min-w-0">
            <x-ref :to="$row->source" class="font-medium text-ink-900">{{ $row->sourceLabel() }}</x-ref>@if ($row->reversal_of_id) <x-badge tone="danger">Reversal</x-badge>@endif
            @if ($detail = $row->sourceDetail($types))<span class="block max-w-xs truncate text-[12px] text-ink-500">{{ $detail }}</span>@endif
        </td>
        <td class="whitespace-nowrap text-ink-600">{{ App\Models\ProjectCostAllocation::CATEGORIES[$row->cost_category] ?? $row->cost_category }}</td>
        <td class="text-ink-600">@if ($row->workPackage)<span class="font-medium text-ink-800">{{ $row->workPackage->code }}</span><span class="block max-w-48 truncate text-[12px] text-ink-500">{{ $row->workPackage->name }}</span>@else — @endif</td>
        <td class="whitespace-nowrap">
            @if ($pay = $row->payment())
                <x-badge :tone="$pay['tone']">{{ $pay['label'] }}</x-badge>
                @if (App\Support\Money::isPositive($pay['due']))<span class="block text-[12px] text-ink-500">{{ App\Support\Format::money($pay['due']) }} due</span>@endif
            @else
                <span class="text-ink-400">—</span>
            @endif
        </td>
        <td @class(['text-right font-medium tabular-nums', 'text-danger-600' => (float) $row->amount < 0])><x-money :value="$row->amount" /></td>
    </tr>
@empty
    <tr><td colspan="6"><x-empty-state icon="bar-chart" title="No cost posted yet" /></td></tr>
@endforelse
