<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Core\Policies\UserPolicy;
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
        // Super Admin holds every named permission ("module.action"); everyone else is deny-by-default.
        // Policy abilities (no dot) still run so record-level rules apply to Super Admin too.
        Gate::before(fn ($user, string $ability) => str_contains($ability, '.') && $user->isSuperAdmin() ? true : null);
        Gate::policy(User::class, UserPolicy::class);

        if ($proxies = config('keyone.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        View::composer(['components.layouts.*'], function ($view) {
            $view->with('branding', $this->app->make(Branding::class));
        });
    }
}
