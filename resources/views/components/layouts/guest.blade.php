@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="login-url" content="{{ route('login') }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $branding->name() }}</title>
    <link rel="icon" href="{{ $branding->faviconUrl() ?? asset('favicon.ico') }}">
    <style>:root { --brand-primary: {{ $branding->primaryColor() }}; --brand-sidebar: {{ $branding->sidebarColor() }}; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <div class="page-progress is-loading" data-page-progress aria-hidden="true"></div>
    <div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
        <aside class="relative hidden overflow-hidden bg-sidebar lg:flex lg:flex-col lg:justify-between p-12 text-white">
            <div class="flex items-center gap-3">
                @if ($branding->logoUrl())
                    <img src="{{ $branding->logoUrl() }}" alt="" class="h-12 w-12 rounded-lg object-contain">
                @else
                    <x-brand-mark size="h-12 w-12" />
                @endif
                <span class="text-xl font-semibold tracking-tight">{{ $branding->name() }}</span>
            </div>
            @if ($branding->tagline())
                <div class="relative z-10 max-w-md">
                    <p class="text-4xl font-semibold leading-tight tracking-tight">{{ $branding->tagline() }}</p>
                </div>
            @endif
            <svg class="pointer-events-none absolute inset-x-0 bottom-0 h-[55%] w-full opacity-25" viewBox="0 0 600 400" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
                <g fill="none" stroke="var(--brand-primary)" stroke-width="1.5">
                    <path d="M40 400V210l80-36v226M120 400V140l90-46v306M210 400V190l74 26v184M284 400V110l100 40v250M384 400V230l66 22v148M450 400V160l90 30v210"/>
                    <path d="M140 160h50M140 190h50M140 220h50M140 250h50M140 280h50M140 310h50M140 340h50M304 170h60M304 200h60M304 230h60M304 260h60M304 290h60M304 320h60M470 210h50M470 240h50M470 270h50M470 300h50"/>
                </g>
            </svg>
        </aside>
        <main class="flex items-center justify-center px-4 py-12 sm:px-8">
            <div class="w-full max-w-[420px]">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <x-brand-mark size="h-10 w-10" />
                    <span class="text-lg font-semibold text-ink-900">{{ $branding->name() }}</span>
                </div>
                {{ $slot }}
            </div>
        </main>
    </div>
    @php($flash = array_filter(['success' => session('success') ?? session('status'), 'error' => session('error')]))
    @if ($flash)
        <script type="application/json" id="flash-data" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">@json($flash)</script>
    @endif
</body>
</html>
