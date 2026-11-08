<x-layouts.app title="Settings">
    <x-page-header title="Settings" :breadcrumbs="['Dashboard' => route('dashboard'), 'Settings' => null]" />

    <form method="POST" action="{{ route('settings.branding') }}" enctype="multipart/form-data" data-ajax-form data-branding-form class="grid gap-6 xl:grid-cols-[2fr_1fr]">
        @csrf
        @method('PUT')

        <x-card title="Branding">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="app_name" label="Application Name" :value="$branding->name()" required />
                <x-form.input name="tagline" label="Sign-in Tagline" :value="$branding->tagline()" />

                @foreach (['primary_color' => ['Primary Color', $branding->primaryColor(), '--brand-primary'], 'sidebar_color' => ['Sidebar Color', $branding->sidebarColor(), '--brand-sidebar']] as $field => [$label, $value, $var])
                    <div data-field>
                        <label class="form-label" for="f-{{ $field }}">{{ $label }} <span class="form-required" aria-hidden="true">*</span></label>
                        <div class="flex items-center gap-3">
                            <input type="color" value="{{ $value }}" class="h-10 w-14 cursor-pointer rounded-control border border-line-strong bg-surface p-1" data-color-picker="{{ $field }}" data-css-var="{{ $var }}" aria-label="{{ $label }} picker">
                            <input type="text" id="f-{{ $field }}" name="{{ $field }}" value="{{ $value }}" maxlength="7" class="form-control font-mono uppercase" data-color-text="{{ $field }}" data-css-var="{{ $var }}" required>
                        </div>
                        <p class="form-error" data-error hidden></p>
                    </div>
                @endforeach

                <div class="sm:col-span-2 grid gap-5 sm:grid-cols-2">
                    <div class="rounded-card border border-line p-4">
                        <p class="form-label">Logo</p>
                        <div class="mb-3 flex h-16 items-center gap-3">
                            @if ($branding->logoUrl())
                                <img src="{{ $branding->logoUrl() }}" alt="" class="h-14 w-14 rounded-lg border border-line object-contain">
                                <x-form.checkbox name="remove_logo" label="Remove" />
                            @else
                                <span class="flex h-14 w-14 items-center justify-center rounded-lg bg-sidebar"><x-brand-mark size="h-9 w-9" /></span>
                            @endif
                        </div>
                        <x-form.file name="logo" accept="image/png,image/jpeg,image/webp" hint="PNG, JPG or WEBP, max 2 MB" />
                    </div>
                    <div class="rounded-card border border-line p-4">
                        <p class="form-label">Favicon</p>
                        <div class="mb-3 flex h-16 items-center gap-3">
                            @if ($branding->faviconUrl())
                                <img src="{{ $branding->faviconUrl() }}" alt="" class="h-10 w-10 rounded border border-line object-contain">
                                <x-form.checkbox name="remove_favicon" label="Remove" />
                            @else
                                <span class="flex h-10 w-10 items-center justify-center rounded border border-line text-ink-400"><x-icon name="image" /></span>
                            @endif
                        </div>
                        <x-form.file name="favicon" accept="image/png,image/x-icon" hint="PNG or ICO, max 512 KB" />
                    </div>
                </div>
            </div>
            <x-form.actions>
                <x-button variant="secondary" type="button" data-reset-colors data-primary="{{ App\Support\Branding::DEFAULT_COLOR }}" data-sidebar="{{ App\Support\Branding::DEFAULT_SIDEBAR_COLOR }}">Default colors</x-button>
                <x-button type="submit" icon="check">Save</x-button>
            </x-form.actions>
        </x-card>

        <x-card title="Preview">
            <div class="overflow-hidden rounded-card border border-line">
                <div class="flex items-center gap-3 bg-sidebar px-4 py-4 text-white">
                    <x-brand-mark size="h-8 w-8" />
                    <span class="font-semibold">{{ $branding->name() }}</span>
                </div>
                <div class="space-y-3 bg-sidebar px-3 pb-4">
                    <span class="nav-link" aria-current="page"><x-icon name="home" />Dashboard</span>
                    <span class="nav-link"><x-icon name="building" />Projects</span>
                </div>
                <div class="space-y-4 bg-canvas p-4">
                    <div class="flex flex-wrap gap-2"><x-button size="sm">Primary</x-button><x-button size="sm" variant="secondary">Secondary</x-button></div>
                    <div class="flex flex-wrap gap-2"><x-badge tone="accent">Accent</x-badge><x-badge tone="success">Active</x-badge></div>
                    <x-stat-card label="Projects" value="12" icon="building" />
                </div>
            </div>
        </x-card>
    </form>
</x-layouts.app>
