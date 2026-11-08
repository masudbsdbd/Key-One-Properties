<?php

namespace App\Providers;

use App\Support\Branding;
use App\Support\Settings;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(Branding::class);
    }

    public function boot(): void
    {
        // Super Admin passes every permission check explicitly; everyone else is deny-by-default.
        Gate::before(fn ($user) => $user->isSuperAdmin() ? true : null);

        if ($proxies = config('keyone.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        View::composer(['components.layouts.*'], function ($view) {
            $view->with('branding', $this->app->make(Branding::class));
        });
    }
}
