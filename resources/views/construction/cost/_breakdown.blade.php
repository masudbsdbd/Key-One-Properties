@php($total = App\Support\Money::add(...array_values($totals)))
<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-5">
    <x-stat-card label="Total Cost" :value="App\Support\Format::money($total, false)" icon="bar-chart" />
    @foreach (App\Models\ProjectCostAllocation::CATEGORIES as $key => $label)
        <x-stat-card :label="$label" :value="App\Support\Format::money($totals[$key], false)" :icon="['material' => 'layers', 'labour' => 'users', 'contractor' => 'user', 'expense' => 'note'][$key]" />
    @endforeach
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <x-card title="Budget vs Actual" :padded="false">
        @if ($packages->isEmpty())
            <x-empty-state icon="layers" title="No work packages" />
        @else
            <table class="data-table">
                <thead><tr><th>Work Package</th><th class="text-right">Budget</th><th class="text-right">Actual</th><th class="text-right">Variance</th></tr></thead>
                <tbody>
                    @foreach ($packages as $package)
                        @php($variance = App\Support\Money::sub($package->budget_amount, App\Support\Money::amount($package->actual)))
                        <tr>
                            <td>{{ $package->label() }}</td>
                            <td class="text-right"><x-money :value="$package->budget_amount" :decimals="false" /></td>
                            <td class="text-right"><x-money :value="$package->actual" :decimals="false" /></td>
                            <td @class(['text-right', 'text-danger-600' => App\Support\Money::cmp($variance, 0) < 0, 'text-success-600' => App\Support\Money::cmp($variance, 0) >= 0])><x-money :value="$variance" :decimals="false" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>

    <x-card title="Cost Entries" :padded="false">
        @can('construction:cost-export', $project)
            <x-slot:actions><x-button size="sm" variant="secondary" icon="download" href="{{ route('construction.cost.export', $project) }}">CSV</x-button></x-slot:actions>
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
