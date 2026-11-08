<?php

namespace App\Providers;

use App\Support\Branding;
use App\Support\Settings;
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
        View::composer(['components.layouts.*'], function ($view) {
            $view->with('branding', $this->app->make(Branding::class));
        });
    }
}
