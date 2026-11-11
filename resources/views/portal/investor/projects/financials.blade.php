@php $sum = fn ($key) => App\Support\Money::amount(App\Support\Money::add('0', ...$rows->pluck($key)->all())); @endphp
<x-layouts.portal :title="$project->name.' · Project Cost'" portal="investor">
    <x-page-header title="Project Cost" :breadcrumbs="['Home' => route('investor.dashboard'), 'Project Progress' => route('investor.projects.index'), $project->name => route('investor.projects.show', $project->public_id), 'Project Cost' => null]" />
    <x-card :title="$project->name" :padded="false">
        <x-slot:actions><x-badge tone="neutral">Operational · not audited</x-badge></x-slot:actions>
        @if ($rows->isEmpty())
            <x-empty-state icon="lock" title="No cost categories shared" />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Category</th><th class="text-right">Shared (allocated)</th><th class="text-right">Unit-tagged</th><th class="text-right">Posted to Date</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr><td class="font-medium text-ink-900">{{ $r['label'] }}</td><td class="text-right"><x-money :value="$r['shared']" /></td><td class="text-right"><x-money :value="$r['direct']" /></td><td class="text-right"><x-money :value="$r['posted']" /></td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr class="font-semibold"><td>Total of shown categories</td><td class="text-right"><x-money :value="$sum('shared')" /></td><td class="text-right"><x-money :value="$sum('direct')" /></td><td class="text-right"><x-money :value="$sum('posted')" /></td></tr></tfoot>
                </table>
            </div>
            <p class="border-t border-line px-5 py-3 text-[13px] text-ink-500">@if ($run)Allocated: cost run {{ $run->run_no }} v{{ $run->calculation_version }}, cost up to {{ App\Support\Format::date($run->cost_cutoff) }} · @endif Posted to date: approved project cost, BDT</p>
        @endif
    </x-card>
</x-layouts.portal>
