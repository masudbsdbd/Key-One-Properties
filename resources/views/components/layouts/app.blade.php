@props(['title' => null])
@php
    $user = auth()->user();
    $sections = \App\Support\Navigation::sections($user);
    $quickCreate = \App\Support\Navigation::quickCreate($user);
    $nameParts = preg_split('/\s+/', trim($branding->name()));
    $nameTail = count($nameParts) > 1 ? array_pop($nameParts) : null;
    $nameHead = implode(' ', $nameParts);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-sidebar="expanded">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="login-url" content="{{ route('login') }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $branding->name() }}</title>
    <link rel="icon" href="{{ $branding->faviconUrl() ?? asset('favicon.ico') }}">
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        try { if (localStorage.getItem('keyone.sidebar') === 'collapsed') document.documentElement.dataset.sidebar = 'collapsed'; } catch (e) {}
    </script>
    <style>:root { --brand-primary: {{ $branding->primaryColor() }}; --brand-sidebar: {{ $branding->sidebarColor() }}; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[80] btn btn-secondary">Skip to content</a>

    {{-- Sidebar --}}
    <div class="fixed inset-0 z-30 bg-ink-900/40 lg:hidden hidden [.sidebar-open_&]:block" data-sidebar-close></div>
    <aside class="app-sidebar fixed inset-y-0 left-0 z-40 flex w-(--spacing-sidebar) -translate-x-full flex-col overflow-hidden bg-sidebar text-white transition-[width,transform] duration-200 lg:translate-x-0 [.sidebar-open_&]:translate-x-0" aria-label="Main navigation">
        <div class="flex h-(--spacing-topbar) shrink-0 items-center gap-3 px-5 pt-2">
            @if ($branding->logoUrl())
                <img src="{{ $branding->logoUrl() }}" alt="" class="h-11 w-11 shrink-0 rounded-lg object-contain">
            @else
                <x-brand-mark class="shrink-0" />
            @endif
            <a href="{{ \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/') }}" class="sidebar-label min-w-0 leading-tight">
                <span class="block truncate text-[19px] font-semibold tracking-tight">{{ $nameHead }}</span>
                @if ($nameTail)
                    <span class="flex items-center gap-2 text-[15px] text-white/85">{{ $nameTail }}<span class="rounded-md bg-accent-500 px-1.5 py-px text-[10px] font-bold tracking-wide text-white">ERP</span></span>
                @endif
            </a>
        </div>

        <nav class="relative z-10 flex-1 overflow-y-auto px-3 pb-6 pt-6">
            @foreach ($sections as $section)
                @if ($section['title'])<p class="nav-section sidebar-label">{{ $section['title'] }}</p>@endif
                <ul class="space-y-1">
                    @foreach ($section['items'] as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="nav-link" @if ($item['current']) aria-current="page" @endif title="{{ $item['label'] }}">
                                <x-icon :name="$item['icon']" size="h-5 w-5 shrink-0" />
                                <span class="sidebar-label truncate">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        <svg class="pointer-events-none absolute inset-x-0 bottom-0 h-56 w-full opacity-[0.16]" viewBox="0 0 248 220" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
            <g fill="none" stroke="var(--brand-primary)" stroke-width="1.4">
                <path d="M20 220V120l40-18v118M60 220V80l46-24v164M106 220V110l38 14v96M144 220V60l52 22v138M196 220V130l34 12v78"/>
                <path d="M72 96h24M72 114h24M72 132h24M72 150h24M72 168h24M72 186h24M156 92h28M156 110h28M156 128h28M156 146h28M156 164h28M156 182h28"/>
            </g>
            <path d="M0 220c60-30 140-40 248-22v22Z" fill="var(--color-sidebar-deep)" opacity=".9"/>
        </svg>
    </aside>

    <div class="app-main min-h-screen transition-[padding] duration-200 lg:pl-(--spacing-sidebar)">
        {{-- Topbar --}}
        <header class="sticky top-0 z-20 flex h-(--spacing-topbar) items-center gap-3 border-b border-line bg-surface/90 px-4 backdrop-blur sm:px-6">
            <button type="button" class="btn btn-ghost btn-icon" data-sidebar-toggle aria-label="Toggle navigation"><x-icon name="menu" size="h-6 w-6" /></button>

            @if (\Illuminate\Support\Facades\Route::has('search'))
                <form action="{{ route('search') }}" method="GET" role="search" class="hidden flex-1 md:block md:max-w-md">
                    <label class="relative block">
                        <span class="sr-only">Search</span>
                        <x-icon name="search" size="h-4 w-4" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500" />
                        <input id="global-search" type="search" name="q" value="{{ request('q') }}" placeholder="Search customers, projects, units..." autocomplete="off" class="form-control h-11 rounded-xl bg-surface-muted pl-10 pr-20">
                        <kbd class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 rounded-md border border-line bg-surface px-2 py-0.5 text-[11px] font-medium text-ink-500">Ctrl + K</kbd>
                    </label>
                </form>
            @endif

            <div class="ml-auto flex items-center gap-1.5 sm:gap-3">
                @if ($quickCreate)
                    <div class="relative">
                        <button type="button" class="btn btn-primary h-11 px-4" data-menu-button aria-controls="menu-create" aria-expanded="false">
                            <x-icon name="plus" size="h-4 w-4" /><span class="hidden sm:inline">Create</span><x-icon name="chevron-down" size="h-4 w-4" />
                        </button>
                        <div id="menu-create" data-menu hidden class="absolute right-0 mt-2 w-52 rounded-card border border-line bg-surface p-1.5 shadow-raised">
                            @foreach ($quickCreate as $item)
                                <a href="{{ $item['url'] }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-surface-muted"><x-icon :name="$item['icon']" size="h-4 w-4 text-ink-500" />{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($user)
                    @include('partials.notifications-menu')

                    <div class="relative">
                        <button type="button" class="flex items-center gap-3 rounded-xl px-1.5 py-1 hover:bg-surface-muted" data-menu-button aria-controls="menu-profile" aria-expanded="false">
                            <x-avatar :name="$user->name" size="h-10 w-10 text-sm" />
                            <span class="hidden text-left leading-tight md:block">
                                <span class="block text-sm font-semibold text-ink-900">{{ $user->name }}</span>
                                <span class="block text-xs text-ink-500">{{ $user->roleLabel() }}</span>
                            </span>
                            <x-icon name="chevron-down" size="h-4 w-4 text-ink-500 hidden md:block" />
                        </button>
                        <div id="menu-profile" data-menu hidden class="absolute right-0 mt-2 w-56 rounded-card border border-line bg-surface p-1.5 shadow-raised">
                            @if (\Illuminate\Support\Facades\Route::has('profile.edit'))
                                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-surface-muted"><x-icon name="user" size="h-4 w-4 text-ink-500" />Profile</a>
                            @endif
                            @if (\Illuminate\Support\Facades\Route::has('logout'))
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-danger-600 hover:bg-danger-50"><x-icon name="logout" size="h-4 w-4" />Log out</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </header>

        <main id="main" class="mx-auto w-full max-w-[1440px] px-4 py-6 sm:px-6 lg:py-8">
            {{ $slot }}
        </main>
    </div>

    <x-confirm-dialog />
    <x-remote-modal />
    @stack('modals')

    @php($flash = array_filter(['success' => session('success'), 'error' => session('error')]))
    @if ($flash)
        <script type="application/json" id="flash-data" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">@json($flash)</script>
    @endif
    @stack('scripts')
</body>
</html>
