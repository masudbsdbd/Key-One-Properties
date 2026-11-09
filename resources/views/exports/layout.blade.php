@php
    $branding = app(App\Support\Branding::class);
    $logo = $pdf ? $branding->logoPath() : $branding->logoUrl();
    $accent = $branding->primaryColor();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} · {{ $branding->name() }}</title>
    <style>
        @page { margin: 18mm 14mm 16mm; }
        * { box-sizing: border-box; }
        body { font-family: {!! $pdf ? "'DejaVu Sans', sans-serif" : "'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif" !!}; font-size: {{ $pdf ? '9pt' : '13px' }}; color: #1b2433; margin: 0; }
        .sheet { {{ $pdf ? '' : 'max-width: 1100px; margin: 0 auto; padding: 32px;' }} }
        .head { width: 100%; border-bottom: 2px solid {{ $accent }}; padding-bottom: 10px; margin-bottom: 14px; }
        .head td { vertical-align: middle; }
        .brand { font-size: {{ $pdf ? '13pt' : '18px' }}; font-weight: bold; }
        .brand-sub { color: #6b7280; font-size: {{ $pdf ? '8pt' : '12px' }}; }
        .logo { height: 38px; margin-right: 10px; }
        .doc-title { text-align: right; font-size: {{ $pdf ? '13pt' : '18px' }}; font-weight: bold; }
        .doc-sub { text-align: right; color: #6b7280; }
        .meta { width: 100%; margin: 0 0 12px; border-collapse: collapse; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .meta .k { color: #6b7280; width: 18%; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #f3f4f6; text-align: left; font-weight: bold; padding: 6px 7px; border-bottom: 1px solid #d1d5db; }
        table.grid td { padding: 5px 7px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.grid tr { page-break-inside: avoid; }
        table.grid tfoot td { font-weight: bold; border-top: 1px solid #9ca3af; border-bottom: 0; background: #fafafa; }
        .r, table.grid th.r, table.grid td.r { text-align: right; white-space: nowrap; }
        .muted { color: #6b7280; }
        .box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; }
        .two { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 14px; }
        .two td { vertical-align: top; width: 50%; }
        .label { color: #6b7280; font-size: {{ $pdf ? '7.5pt' : '11px' }}; text-transform: uppercase; letter-spacing: .04em; }
        .sum { width: 45%; margin-left: auto; margin-top: 10px; border-collapse: collapse; }
        .sum td { padding: 4px 7px; }
        .sum .grand td { border-top: 1px solid #9ca3af; font-weight: bold; font-size: {{ $pdf ? '10pt' : '15px' }}; }
        .signs { width: 100%; margin-top: 48px; }
        .signs td { width: 33%; text-align: center; padding: 0 12px; }
        .signs span { display: block; border-top: 1px solid #9ca3af; padding-top: 4px; color: #6b7280; }
        .note { margin-top: 12px; }
        .stamp { display: inline-block; padding: 2px 8px; border-radius: 10px; border: 1px solid #9ca3af; font-size: {{ $pdf ? '8pt' : '11px' }}; }
        .foot { {{ $pdf ? 'position: fixed; bottom: -10mm; left: 0; right: 0;' : 'margin-top: 24px;' }} color: #9ca3af; font-size: {{ $pdf ? '7.5pt' : '11px' }}; }
        .toolbar { position: sticky; top: 0; background: #fff; border-bottom: 1px solid #e5e7eb; padding: 10px 32px; display: flex; gap: 8px; justify-content: flex-end; }
        .toolbar button { font: inherit; padding: 7px 14px; border-radius: 8px; border: 1px solid #d1d5db; background: #fff; cursor: pointer; }
        .toolbar button.primary { background: {{ $accent }}; border-color: {{ $accent }}; color: #fff; }
        @media print { .toolbar { display: none; } .sheet { padding: 0; max-width: none; } }
    </style>
</head>
<body>
    @unless ($pdf)
        <div class="toolbar"><button type="button" data-close>Close</button><button type="button" class="primary" data-print>Print</button></div>
    @endunless
    @if ($pdf)
        <div class="foot">{{ $branding->name() }} · {{ App\Support\Format::datetime(now()) }} · {{ auth()->user()?->name }}</div>
    @endif
    <div class="sheet">
        <table class="head">
            <tr>
                <td>
                    @if ($logo)<img src="{{ $logo }}" class="logo" alt="" style="vertical-align: middle">@endif
                    <span style="display:inline-block; vertical-align: middle">
                        <span class="brand">{{ $branding->name() }}</span>
                        @if ($branding->tagline())<br><span class="brand-sub">{{ $branding->tagline() }}</span>@endif
                    </span>
                </td>
                <td>
                    <div class="doc-title">{{ $title }}</div>
                    @isset($subtitle)<div class="doc-sub">{{ $subtitle }}</div>@endisset
                </td>
            </tr>
        </table>

        {{ $slot ?? '' }}
        @yield('body')

        @unless ($pdf)
            <div class="foot">{{ $branding->name() }} · {{ App\Support\Format::datetime(now()) }} · {{ auth()->user()?->name }}</div>
        @endunless
    </div>
    @unless ($pdf)
        <script nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">
            document.querySelector('[data-print]').addEventListener('click', () => window.print());
            document.querySelector('[data-close]').addEventListener('click', () => window.close());
            window.addEventListener('load', () => setTimeout(() => window.print(), 300));
        </script>
    @endunless
</body>
</html>
