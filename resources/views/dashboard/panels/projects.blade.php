@if (! $data['rows'])
    <x-empty-state icon="building" title="No active projects" class="py-8" />
@else
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Project</th><th class="w-48">Construction</th>
                    @if ($data['show_cost'])<th class="w-48">Budget Used</th>@endif
                    <th class="text-right">Units Sold</th>
                    @if ($data['show_sales'])<th class="text-right">Sold Value</th>@endif
                    @if ($data['show_paid'])<th class="w-44">Collected</th>@endif
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
                                <p class="mt-0.5 text-[12px] text-ink-500"><x-money :value="$r['spent']" /> of <x-money :value="$r['budget']" /></p>
                            </td>
                        @endif
                        <td class="text-right tabular-nums">{{ $r['units_sold'] }} / {{ $r['units'] }}</td>
                        @if ($data['show_sales'])<td class="text-right font-medium"><x-money :value="$r['sold_value']" /></td>@endif
                        @if ($data['show_paid'])
                            <td>@if ($r['collected_pct'] !== null)<x-progress :value="$r['collected_pct']" />@endif<p class="mt-0.5 text-[12px] text-ink-500"><x-money :value="$r['collected']" /></p></td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
