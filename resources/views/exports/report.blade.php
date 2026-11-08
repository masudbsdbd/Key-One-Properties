@extends('exports.layout', ['subtitle' => count($rows).' '.\Illuminate\Support\Str::plural('row', count($rows))])
@section('body')
    @if ($meta)
        <table class="meta">
            @foreach ($meta as $k => $v)
                <tr><td class="k">{{ $k }}</td><td>{{ $v }}</td></tr>
            @endforeach
        </table>
    @endif
    <table class="grid">
        <thead>
            <tr>@foreach ($columns as $c)<th @class(['r' => in_array($c['type'], ['money', 'qty', 'number'], true)])>{{ $c['label'] }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $i => $cell)<td @class(['r' => in_array($columns[$i]['type'], ['money', 'qty', 'number'], true)])>{{ $cell }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="muted">No records</td></tr>
            @endforelse
        </tbody>
        @if ($totals)
            <tfoot><tr>@foreach ($totals as $i => $cell)<td @class(['r' => in_array($columns[$i]['type'], ['money', 'qty', 'number'], true)])>{{ $i === 0 && $cell === '' ? 'Total' : $cell }}</td>@endforeach</tr></tfoot>
        @endif
    </table>
@endsection
