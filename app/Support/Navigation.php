<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

class Navigation
{
    /**
     * @return array<int, array{title: ?string, items: array<int, array<string, mixed>>}>
     */
    public static function sections(?Authenticatable $user): array
    {
        $sections = [
            [
                'title' => null,
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard', 'active' => 'dashboard'],
                    ['label' => 'Projects', 'icon' => 'building', 'route' => 'projects.index', 'active' => 'projects.*', 'can' => ['projects.view', 'projects.view_all']],
                    ['label' => 'Units (Flats)', 'icon' => 'door', 'route' => 'flats.index', 'active' => 'flats.*', 'can' => 'flats.view'],
                    ['label' => 'Customers', 'icon' => 'users', 'route' => 'customers.index', 'active' => 'customers.*', 'can' => 'customers.view'],
                ],
            ],
            [
                'title' => 'Administration',
                'items' => [
                    ['label' => 'Users', 'icon' => 'user', 'route' => 'users.index', 'active' => 'users.*', 'can' => 'users.view'],
                    ['label' => 'Roles & Permissions', 'icon' => 'shield', 'route' => 'roles.index', 'active' => 'roles.*', 'can' => 'roles.view'],
                    ['label' => 'Departments', 'icon' => 'briefcase', 'route' => 'departments.index', 'active' => 'departments.*', 'can' => 'departments.view'],
                    ['label' => 'Activity Log', 'icon' => 'activity', 'route' => 'activity.index', 'active' => 'activity.*', 'can' => 'activity.view'],
                    ['label' => 'Settings', 'icon' => 'settings', 'route' => 'settings.edit', 'active' => 'settings.*', 'can' => 'settings.manage'],
                ],
            ],
        ];

        return collect($sections)
            ->map(function (array $section) use ($user) {
                $section['items'] = collect($section['items'])
                    ->filter(fn (array $item) => Route::has($item['route']))
                    ->filter(fn (array $item) => ! isset($item['can']) || ($user && $user->canAny((array) $item['can'])))
                    ->map(fn (array $item) => $item + [
                        'url' => route($item['route']),
                        'current' => request()->routeIs($item['active']),
                    ])
                    ->values()
                    ->all();

                return $section;
            })
            ->filter(fn (array $section) => $section['items'] !== [])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, icon: string, url: string}>
     */
    public static function quickCreate(?Authenticatable $user): array
    {
        $items = [
            ['label' => 'Project', 'icon' => 'building', 'route' => 'projects.index', 'query' => ['create' => 1], 'can' => 'projects.create'],
            ['label' => 'Unit (Flat)', 'icon' => 'door', 'route' => 'flats.index', 'query' => ['create' => 1], 'can' => 'flats.create'],
            ['label' => 'Customer', 'icon' => 'user-plus', 'route' => 'customers.index', 'query' => ['create' => 1], 'can' => 'customers.create'],
        ];

        return collect($items)
            ->filter(fn (array $item) => Route::has($item['route']) && $user && $user->can($item['can']))
            ->map(fn (array $item) => [
                'label' => $item['label'],
                'icon' => $item['icon'],
                'url' => route($item['route'], $item['query']),
            ])
            ->values()
            ->all();
    }
}
