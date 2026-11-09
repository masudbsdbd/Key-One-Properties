@php
    $types = array_values($report->columns());
    $cell = function ($value, $type) {
        if ($value === null || $value === '') return '<span class="text-ink-400">—</span>';
        return match ($type) {
            'money' => e(App\Support\Format::money($value)),
            'qty' => e(App\Support\Format::qty($value)),
            'date' => e(App\Support\Format::date($value instanceof \DateTimeInterface ? Carbon\Carbon::instance($value) : Carbon\Carbon::parse($value))),
            'number' => e(is_numeric($value) ? rtrim(rtrim(number_format((float) $value, 2), '0'), '.') : $value),
            default => e($value),
        };
    };
@endphp
@forelse ($rows as $row)
    <tr>
        @foreach ($row as $i => $value)
            <td @class(['whitespace-nowrap text-right tabular-nums' => in_array($types[$i] ?? 'text', ['money', 'qty', 'number'], true), 'whitespace-nowrap' => ($types[$i] ?? '') === 'date'])>{!! $cell($value, $types[$i] ?? 'text') !!}</td>
        @endforeach
    </tr>
@empty
    <tr><td colspan="{{ count($types) }}"><x-empty-state icon="file" title="No data for these filters" /></td></tr>
@endforelse
@if ($totals && $rows->isNotEmpty())
    <tr class="bg-surface-muted font-semibold">
        @foreach ($totals as $i => $value)
            <td @class(['text-right tabular-nums whitespace-nowrap' => in_array($types[$i] ?? 'text', ['money', 'qty', 'number'], true)])>{!! $value === null ? '' : $cell($value, $i === 0 ? 'text' : ($types[$i] ?? 'text')) !!}</td>
        @endforeach
    </tr>
@endif
