@if ($rows->isEmpty())
    <x-empty-state icon="building" title="Nothing posted for {{ $project ? 'the units of this project' : 'any project' }} yet" />
@else
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ $project ? 'Unit' : 'Project' }}</th>@foreach ($columns as $label)<th class="w-32 whitespace-normal text-right leading-tight">{{ $label }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="whitespace-nowrap font-medium">
                            @if ($project)<x-ref :to="$row['item']">{{ $row['label'] }}</x-ref>
                            @else<a href="{{ route('accounting.projects', ['project' => $row['item']->public_id, 'period' => $range['preset'], 'from' => $range['from'], 'to' => $range['to']]) }}" class="ref-link" data-report-link>{{ $row['label'] }}</a>@endif
                        </td>
                        @foreach ($columns as $key => $label)
                            <td @class(['text-right tabular-nums', 'font-semibold' => $key === 'profit', 'text-danger-600' => App\Support\Money::cmp($row[$key], 0) < 0])><x-money :value="$row[$key]" :decimals="false" /></td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr class="bg-accent-50/60 font-semibold text-ink-900"><td>Total</td>@foreach ($columns as $key => $label)<td class="text-right tabular-nums"><x-money :value="$totals[$key]" :decimals="false" /></td>@endforeach</tr></tfoot>
        </table>
    </div>
@endif
