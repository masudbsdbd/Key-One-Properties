@extends('exports.layout')
@section('body')
    @if ($parties)
        <table class="two">
            <tr>
                @foreach ($parties as $label => $partyLines)
                    <td style="{{ $loop->first ? 'padding-right: 8px' : 'padding-left: 8px' }}">
                        <div class="box">
                            <div class="label">{{ $label }}</div>
                            @foreach ($partyLines as $line)<div @if ($loop->first) style="font-weight: bold" @endif>{{ $line }}</div>@endforeach
                        </div>
                    </td>
                @endforeach
                @if (count($parties) === 1)<td></td>@endif
            </tr>
        </table>
    @endif

    @if ($meta)
        <table class="meta">
            @foreach (array_chunk($meta, 2, true) as $pair)
                <tr>
                    @foreach ($pair as $k => $v)<td class="k">{{ $k }}</td><td>{{ $v }}</td>@endforeach
                    @if (count($pair) === 1)<td></td><td></td>@endif
                </tr>
            @endforeach
        </table>
    @endif

    @if ($columns)
        <table class="grid">
            <thead><tr><th style="width: 24px">#</th>@foreach ($columns as $c)<th @class(['r' => in_array($c['type'], ['money', 'qty', 'number'], true)])>{{ $c['label'] }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($lines as $row)
                    <tr><td class="muted">{{ $loop->iteration }}</td>@foreach ($row as $i => $cell)<td @class(['r' => in_array($columns[$i]['type'], ['money', 'qty', 'number'], true)])>{{ $cell }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($summary)
        <table class="sum">
            @foreach ($summary as $label => $value)
                <tr @class(['grand' => $loop->last])><td>{{ $label }}</td><td class="r">{{ $value }}</td></tr>
            @endforeach
        </table>
    @endif

    @if ($notes)
        <div class="note"><div class="label">Notes</div><div style="white-space: pre-line">{{ $notes }}</div></div>
    @endif

    @if ($signatures)
        <table class="signs"><tr>@foreach ($signatures as $s)<td><span>{{ $s }}</span></td>@endforeach</tr></table>
    @endif
@endsection
