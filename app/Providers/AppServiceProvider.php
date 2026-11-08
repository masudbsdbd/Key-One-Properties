<?php

namespace App\Providers;

use App\Models\ConstructionProgressUpdate;
use App\Models\Customer;
use App\Models\Flat;
use App\Models\Project;
use App\Models\User;
use App\Modules\Construction\Policies\ConstructionAccess;
use App\Modules\Contractors\Policies\ContractorAccess;
use App\Modules\Core\Policies\UserPolicy;
use App\Modules\Customers\Policies\CustomerPolicy;
use App\Modules\Flats\Policies\FlatPolicy;
use App\Modules\Projects\Policies\ProjectPolicy;
use App\Modules\Purchasing\Policies\PurchasingAccess;
use App\Support\Attachments\AttachableRegistry;
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
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Flat::class, FlatPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        ConstructionAccess::register();
        PurchasingAccess::register();
        ContractorAccess::register();

        // Progress evidence: visible with the project; changeable by the submitter or an approver while still submitted.
        AttachableRegistry::register('construction-progress', ConstructionProgressUpdate::class, 'construction-progress:files-view', 'construction-progress:files-manage');
        Gate::define('construction-progress:files-view', fn ($user, ConstructionProgressUpdate $owner) => Gate::forUser($user)->allows('construction:view', $owner->project));
        Gate::define('construction-progress:files-manage', fn ($user, ConstructionProgressUpdate $owner) => $owner->status === 'submitted'
            && (($owner->submitted_by === $user->id && Gate::forUser($user)->allows('construction:progress-submit', $owner->project))
                || Gate::forUser($user)->allows('construction:progress-approve', $owner->project)));

        if ($proxies = config('keyone.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        View::composer(['components.layouts.*'], function ($view) {
            $view->with('branding', $this->app->make(Branding::class));
        });
    }
}
