<?php

namespace App\Support;

use App\Models\PasswordResetRequest;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

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
                    ['label' => 'Customers', 'icon' => 'users', 'route' => 'customers.index', 'active' => 'customers.*', 'can' => ['customers.view', 'customers.view_all']],
                ],
            ],
            [
                'title' => 'Construction',
                'items' => [
                    ['label' => 'Construction', 'icon' => 'stairs', 'route' => 'construction.index', 'active' => 'construction.*', 'can' => 'construction.view'],
                    ['label' => 'Labour', 'icon' => 'users', 'route' => 'labour.index', 'active' => 'labour.*', 'can' => 'construction.view'],
                ],
            ],
            [
                'title' => 'Inventory',
                'items' => [
                    ['label' => 'Stock', 'icon' => 'layers', 'route' => 'stock.index', 'active' => 'stock.*', 'can' => 'materials.view'],
                    ['label' => 'Materials', 'icon' => 'tag', 'route' => 'materials.index', 'active' => 'materials.*', 'can' => 'materials.view'],
                    ['label' => 'Warehouses', 'icon' => 'building', 'route' => 'warehouses.index', 'active' => 'warehouses.*', 'can' => 'materials.view'],
                    ['label' => 'Borrows', 'icon' => 'refresh', 'route' => 'borrows.index', 'active' => 'borrows.*', 'can' => 'materials.view'],
                    ['label' => 'Transfers', 'icon' => 'arrow-right', 'route' => 'transfers.index', 'active' => 'transfers.*', 'can' => 'materials.view'],
                    ['label' => 'Adjustments', 'icon' => 'edit', 'route' => 'adjustments.index', 'active' => 'adjustments.*', 'can' => 'materials.view'],
                ],
            ],
            [
                'title' => 'Purchasing',
                'items' => [
                    ['label' => 'Suppliers', 'icon' => 'briefcase', 'route' => 'suppliers.index', 'active' => 'suppliers.*', 'can' => 'suppliers.view'],
                    ['label' => 'Purchase Orders', 'icon' => 'note', 'route' => 'purchase-orders.index', 'active' => ['purchase-orders.*', 'goods-receipts.*', 'purchase-returns.*'], 'can' => 'purchases.view'],
                    ['label' => 'Supplier Bills', 'icon' => 'file', 'route' => 'supplier-bills.index', 'active' => 'supplier-bills.*', 'can' => 'purchases.view'],
                    ['label' => 'Supplier Payments', 'icon' => 'tag', 'route' => 'supplier-payments.index', 'active' => 'supplier-payments.*', 'can' => 'purchases.view'],
                ],
            ],
            [
                'title' => 'Contractors',
                'items' => [
                    ['label' => 'Contractors', 'icon' => 'user', 'route' => 'contractors.index', 'active' => 'contractors.*', 'can' => 'contractors.view'],
                    ['label' => 'Work Orders', 'icon' => 'check-square', 'route' => 'work-orders.index', 'active' => 'work-orders.*', 'can' => 'contractors.view'],
                    ['label' => 'Contractor Bills', 'icon' => 'file', 'route' => 'contractor-bills.index', 'active' => 'contractor-bills.*', 'can' => 'contractors.view'],
                    ['label' => 'Contractor Payments', 'icon' => 'tag', 'route' => 'contractor-payments.index', 'active' => 'contractor-payments.*', 'can' => 'contractors.view'],
                ],
            ],
            [
                'title' => 'Expenses',
                'items' => [
                    ['label' => 'Expenses', 'icon' => 'note', 'route' => 'expenses.index', 'active' => 'expenses.*', 'can' => ['expenses.view.general', 'expenses.view.project']],
                    ['label' => 'Expense Categories', 'icon' => 'list', 'route' => 'expense-categories.index', 'active' => 'expense-categories.*', 'can' => 'expenses.categories'],
                ],
            ],
            [
                'title' => 'Administration',
                'items' => [
                    ['label' => 'Users', 'icon' => 'user', 'route' => 'users.index', 'active' => 'users.*', 'can' => 'users.view'],
                    ['label' => 'Reset Requests', 'icon' => 'key', 'route' => 'password-requests.index', 'active' => 'password-requests.*', 'can' => 'users.reset_password', 'badge' => fn () => PasswordResetRequest::query()->where('status', 'pending')->count()],
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
                    ->map(fn (array $item) => [
                        'url' => route($item['route']),
                        'current' => request()->routeIs(...(array) $item['active']),
                        'badge' => isset($item['badge']) ? ($item['badge'])() : null,
                    ] + $item)
                    ->values()
                    ->all();

                return $section;
            })
            ->filter(fn (array $section) => $section['items'] !== [])
            ->map(fn (array $section) => $section + ['key' => $section['title'] ? Str::slug($section['title']) : 'main', 'open' => collect($section['items'])->contains('current', true)])
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
