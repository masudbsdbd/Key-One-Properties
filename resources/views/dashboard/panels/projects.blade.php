@if (! $data['rows'])
    <x-empty-state icon="building" title="No active projects" class="py-8" />
@else
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Project</th><th class="w-48">Construction</th>
                    @if ($data['show_cost'])<th class="w-60">Budget Used</th>@endif
                    <th class="text-right">Units Sold</th>
                    @if ($data['show_sales'])<th class="text-right">Sold Value</th>@endif
                    @if ($data['show_paid'])<th class="w-44">Collected</th>@endif
                    @if ($data['show_outlook'])<th class="text-right">Expected Profit</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach ($data['rows'] as $r)
                    <tr>
                        <td>
                            <x-ref :href="$r['href']" class="font-medium text-ink-900">{{ $r['name'] }}</x-ref>
                            <p class="text-[13px] text-ink-500">{{ $r['code'] }}@if ($r['flag']) · <span class="font-medium text-danger-600">Cost ahead of progress</span>@endif</p>
                        </td>
                        <td><x-progress :value="$r['progress']" /></td>
                        @if ($data['show_cost'])
                            <td>
                                @if ($r['used'] !== null)<x-progress :value="$r['used']" />@else<span class="text-ink-400">—</span>@endif
                                <p class="mt-0.5 whitespace-nowrap text-[12px] tabular-nums text-ink-500">{{ App\Support\Format::money($r['spent'], false) }}@if ($r['budget'] !== null && (float) $r['budget']) of {{ App\Support\Format::money($r['budget'], false) }}@endif</p>
                            </td>
                        @endif
                        <td class="text-right tabular-nums">{{ $r['units_sold'] }} / {{ $r['units'] }}</td>
                        @if ($data['show_sales'])<td class="text-right font-medium"><x-money :value="$r['sold_value']" /></td>@endif
                        @if ($data['show_paid'])
                            <td>@if ($r['collected_pct'] !== null)<x-progress :value="$r['collected_pct']" />@endif<p class="mt-0.5 whitespace-nowrap text-[12px] tabular-nums text-ink-500">{{ App\Support\Format::money($r['collected'], false) }}</p></td>
                        @endif
                        @if ($data['show_outlook'])
                            <td class="whitespace-nowrap text-right">
                                @if ($r['outlook'])
                                    <p @class(['font-semibold tabular-nums', 'text-danger-600' => (float) $r['outlook']['profit'] < 0, 'text-success-600' => (float) $r['outlook']['profit'] >= 0])>{{ App\Support\Format::money($r['outlook']['profit'], false) }}@if ($r['outlook']['margin'] !== null) <span class="text-[12px] font-medium">· {{ App\Support\Format::percent($r['outlook']['margin']) }}</span>@endif</p>
                                    <p class="text-[12px] tabular-nums text-ink-500">{{ App\Support\Format::money($r['outlook']['revenue'], false) }} sales − {{ App\Support\Format::money($r['budget'], false) }} budget</p>
                                @else
                                    <span class="text-ink-400" data-tooltip="Set a budget on the project">—</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
