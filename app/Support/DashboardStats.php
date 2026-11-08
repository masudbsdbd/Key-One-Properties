<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Department;
use App\Models\Flat;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Real counts only; each module adds its own card when it exists and the user may see it.
 */
class DashboardStats
{
    /**
     * @return array<int, array{label: string, value: string, icon: string, href: ?string}>
     */
    public function for(User $user): array
    {
        $cards = [];

        if ($user->canAny(['projects.view', 'projects.view_all'])) {
            $projects = Project::query()->visibleTo($user);
            $cards[] = ['label' => 'Projects', 'value' => number_format((clone $projects)->count()), 'icon' => 'building', 'href' => route('projects.index')];
            $cards[] = ['label' => 'Active Projects', 'value' => number_format((clone $projects)->where('status', 'active')->count()), 'icon' => 'activity', 'href' => route('projects.index')];
        }

        if ($user->can('flats.view')) {
            $flats = Flat::query()->visibleTo($user);
            $cards[] = ['label' => 'Units', 'value' => number_format((clone $flats)->count()), 'icon' => 'door', 'href' => route('flats.index')];
            $cards[] = ['label' => 'Available Units', 'value' => number_format((clone $flats)->where('status', 'available')->count()), 'icon' => 'check-square', 'href' => route('flats.index')];
        }

        if ($user->canAny(['customers.view', 'customers.view_all'])) {
            $cards[] = ['label' => 'Customers', 'value' => number_format(Customer::query()->visibleTo($user)->count()), 'icon' => 'users', 'href' => route('customers.index')];
        }

        if ($user->can('users.view')) {
            $cards[] = ['label' => 'Active Users', 'value' => number_format(User::query()->active()->count()), 'icon' => 'users', 'href' => Route::has('users.index') ? route('users.index') : null];
        }

        if ($user->can('departments.view')) {
            $cards[] = ['label' => 'Departments', 'value' => number_format(Department::query()->count()), 'icon' => 'briefcase', 'href' => Route::has('departments.index') ? route('departments.index') : null];
        }

        return $cards;
    }
}
