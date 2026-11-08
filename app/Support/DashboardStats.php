<?php

namespace App\Support;

use App\Models\Department;
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

        if ($user->can('users.view')) {
            $cards[] = ['label' => 'Active Users', 'value' => number_format(User::query()->active()->count()), 'icon' => 'users', 'href' => Route::has('users.index') ? route('users.index') : null];
        }

        if ($user->can('departments.view')) {
            $cards[] = ['label' => 'Departments', 'value' => number_format(Department::query()->count()), 'icon' => 'briefcase', 'href' => Route::has('departments.index') ? route('departments.index') : null];
        }

        return $cards;
    }
}
