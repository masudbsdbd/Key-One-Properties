<?php

namespace App\Support;

/**
 * Single source of truth for permission names, grouped for the role editor.
 */
class Permissions
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'Dashboard' => [
                'dashboard.view' => 'View dashboard',
            ],
            'Users' => [
                'users.view' => 'View users',
                'users.create' => 'Create users',
                'users.update' => 'Edit users',
                'users.delete' => 'Delete users',
                'users.manage_2fa' => 'Manage two-factor settings',
                'users.reset_password' => 'Reset passwords',
            ],
            'Roles & Permissions' => [
                'roles.view' => 'View roles',
                'roles.manage' => 'Create and edit roles',
            ],
            'Departments' => [
                'departments.view' => 'View departments',
                'departments.manage' => 'Manage departments',
            ],
            'Projects' => [
                'projects.view' => 'View assigned projects',
                'projects.view_all' => 'View all projects',
                'projects.create' => 'Create projects',
                'projects.update' => 'Edit projects',
                'projects.archive' => 'Archive projects',
                'projects.manage_stages' => 'Manage stages',
                'projects.update_progress' => 'Record progress',
                'projects.manage_media' => 'Manage gallery',
                'projects.manage_members' => 'Manage team',
            ],
            'Units (Flats)' => [
                'flats.view' => 'View units',
                'flats.create' => 'Create units',
                'flats.update' => 'Edit units',
                'flats.delete' => 'Delete units',
                'flats.bulk_create' => 'Bulk create units',
                'flats.change_price' => 'Change price',
                'flats.change_status' => 'Change status',
                'flats.manage_media' => 'Manage unit media',
                'buildings.manage' => 'Manage buildings and floors',
            ],
            'Customers' => [
                'customers.view' => 'View own customers',
                'customers.view_all' => 'View all customers',
                'customers.create' => 'Create customers',
                'customers.update' => 'Edit customers',
                'customers.archive' => 'Archive customers',
                'customers.manage_documents' => 'Manage documents',
                'customers.view_sensitive' => 'View NID / passport',
            ],
            'Approvals' => [
                'approvals.request' => 'Request approval',
                'approvals.approve' => 'Approve or reject',
            ],
            'System' => [
                'activity.view' => 'View activity log',
                'settings.manage' => 'Manage settings',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return collect(self::groups())->flatMap(fn (array $items) => array_keys($items))->values()->all();
    }

    /**
     * Default permission sets for seeded roles.
     *
     * @return array<string, array<int, string>>
     */
    public static function defaultRoles(): array
    {
        $all = self::all();

        return [
            'Admin' => $all,
            'Project Manager' => [
                'dashboard.view', 'departments.view',
                'projects.view', 'projects.update', 'projects.manage_stages', 'projects.update_progress', 'projects.manage_media', 'projects.manage_members',
                'flats.view', 'flats.create', 'flats.update', 'flats.bulk_create', 'flats.change_status', 'flats.manage_media', 'buildings.manage',
                'approvals.request',
            ],
            'Sales Executive' => [
                'dashboard.view',
                'projects.view', 'flats.view',
                'customers.view', 'customers.create', 'customers.update', 'customers.manage_documents',
                'approvals.request',
            ],
            'Viewer' => [
                'dashboard.view', 'projects.view', 'flats.view', 'customers.view',
            ],
        ];
    }
}
