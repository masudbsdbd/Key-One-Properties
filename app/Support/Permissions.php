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
            'Construction' => [
                'construction.view' => 'View construction',
                'construction.manage' => 'Manage work packages and milestones',
                'construction.progress.submit' => 'Submit progress',
                'construction.progress.approve' => 'Approve progress',
                'construction.labour.manage' => 'Record labour',
                'construction.labour.approve' => 'Approve labour',
                'construction.cost.view' => 'View project cost',
                'construction.cost.export' => 'Export project cost',
            ],
            'Inventory' => [
                'materials.view' => 'View materials and stock',
                'materials.manage' => 'Manage materials and warehouses',
                'materials.receive' => 'Receive stock',
                'materials.issue' => 'Issue stock',
                'materials.adjust.request' => 'Request stock adjustment',
                'materials.adjust.approve' => 'Approve stock adjustment',
                'materials.borrow.request' => 'Request borrow',
                'materials.borrow.approve' => 'Approve borrow',
                'materials.borrow.issue' => 'Issue borrowed material',
                'materials.borrow.return' => 'Return borrowed material',
                'materials.borrow.convert' => 'Convert borrow to transfer',
                'materials.transfer.request' => 'Request transfer',
                'materials.transfer.approve' => 'Approve transfer',
                'materials.transfer.post' => 'Post transfer',
                'materials.movement.reverse' => 'Reverse stock movements',
                'materials.valuation.view' => 'View stock valuation',
                'materials.export' => 'Export stock',
            ],
            'Suppliers' => [
                'suppliers.view' => 'View suppliers',
                'suppliers.create' => 'Create suppliers',
                'suppliers.update' => 'Edit suppliers',
                'suppliers.ledger.view' => 'View supplier ledger',
                'suppliers.ledger.export' => 'Export supplier ledger',
            ],
            'Purchasing' => [
                'purchases.view' => 'View purchases',
                'purchases.create' => 'Create purchase orders',
                'purchases.approve' => 'Approve purchase orders',
                'purchases.receive' => 'Receive goods',
                'purchases.bill.manage' => 'Record supplier bills',
                'purchases.bill.approve' => 'Approve supplier bills',
                'purchases.payment.create' => 'Record supplier payments',
                'purchases.payment.approve' => 'Approve supplier payments',
                'purchases.return' => 'Return goods to supplier',
            ],
            'Contractors' => [
                'contractors.view' => 'View contractors',
                'contractors.create' => 'Create contractors',
                'contractors.update' => 'Edit contractors',
                'contractors.workorder.create' => 'Create work orders',
                'contractors.workorder.approve' => 'Approve work orders',
                'contractors.measurement.manage' => 'Record measurements',
                'contractors.measurement.approve' => 'Approve measurements',
                'contractors.bill.create' => 'Create contractor bills',
                'contractors.bill.approve' => 'Approve contractor bills',
                'contractors.payment.create' => 'Record contractor payments',
                'contractors.payment.approve' => 'Approve contractor payments',
                'contractors.ledger.view' => 'View contractor ledger',
            ],
            'Expenses' => [
                'expenses.view.general' => 'View general expenses',
                'expenses.view.project' => 'View project expenses',
                'expenses.create.general' => 'Create general expenses',
                'expenses.create.project' => 'Create project expenses',
                'expenses.approve' => 'Approve expenses',
                'expenses.pay' => 'Record expense payment',
                'expenses.reverse' => 'Reverse expenses',
                'expenses.categories' => 'Manage expense categories',
                'expenses.export' => 'Export expenses',
            ],
            'Finance' => [
                'financial.events.view' => 'View financial events',
                'financial.events.reconcile' => 'Run reconciliation',
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
