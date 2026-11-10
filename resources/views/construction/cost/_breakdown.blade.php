@php($total = App\Support\Money::add(...array_values($totals)))
<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-5">
    <x-stat-card label="Total Cost" :value="App\Support\Format::money($total, false)" icon="bar-chart" />
    @foreach (App\Models\ProjectCostAllocation::CATEGORIES as $key => $label)
        <x-stat-card :label="$label" :value="App\Support\Format::money($totals[$key], false)" :icon="['material' => 'layers', 'labour' => 'users', 'contractor' => 'user', 'expense' => 'note'][$key]" />
    @endforeach
</div>

<div class="mt-6">
    <x-card title="Cost Entries" :padded="false">
        @can('construction:cost-export', $project)
            <x-slot:actions><x-export-menu :url="route('construction.cost.export', $project)" :table="false" /></x-slot:actions>
        @endcan
        @if ($rows->isEmpty())
            <x-empty-state icon="bar-chart" title="No cost posted yet" />
        @else
            <div class="max-h-[420px] overflow-y-auto">
                <table class="data-table">
                    <thead class="sticky top-0"><tr><th>Date</th><th>Category</th><th>Source</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="whitespace-nowrap">{{ App\Support\Format::date($row->effective_at) }}</td>
                                <td>{{ App\Models\ProjectCostAllocation::CATEGORIES[$row->cost_category] ?? $row->cost_category }}</td>
                                <td class="text-ink-600">{{ Str::headline(class_basename($row->source_type)) }}@if ($row->reversal_of_id) <x-badge tone="danger">Reversal</x-badge>@endif</td>
                                <td class="text-right"><x-money :value="$row->amount" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</div>
