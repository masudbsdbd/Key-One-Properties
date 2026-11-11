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
                'projects.export' => 'Export projects',
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
                'flats.export' => 'Export units',
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
                'customers.export' => 'Export customers',
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
                'purchases.export' => 'Export purchasing lists',
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
                'contractors.export' => 'Export contractor lists',
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
            'Sales' => [
                'sales.booking.view' => 'View bookings',
                'sales.booking.create' => 'Create bookings',
                'sales.booking.edit' => 'Edit draft bookings',
                'sales.booking.submit' => 'Submit bookings',
                'sales.booking.approve' => 'Approve bookings',
                'sales.booking.cancel.request' => 'Request cancellation',
                'sales.booking.cancel.approve' => 'Approve cancellation',
                'sales.sale.view' => 'View sales',
                'sales.sale.create' => 'Create sales',
                'sales.sale.approve' => 'Approve sales',
                'sales.sale.agreement.manage' => 'Manage sale agreements',
                'sales.sale.registration.manage' => 'Manage registration',
                'sales.sale.handover.manage' => 'Manage handover',
                'sales.price.view' => 'View prices',
                'sales.price.change.request' => 'Request price changes',
                'sales.price.change.approve' => 'Approve price changes',
                'sales.price.history.view' => 'View price history',
                'sales.refund.view' => 'View refunds',
                'sales.refund.request' => 'Request refunds',
                'sales.refund.approve' => 'Approve refunds',
                'sales.refund.pay' => 'Pay refunds',
                'sales.refund.reverse' => 'Reverse refunds',
                'sales.export' => 'Export sales lists',
            ],
            'Collections' => [
                'collections.plan.view' => 'View installment plans',
                'collections.plan.create' => 'Create installment plans',
                'collections.plan.approve' => 'Approve installment plans',
                'collections.plan.reschedule.request' => 'Request reschedules',
                'collections.plan.reschedule.approve' => 'Approve reschedules',
                'collections.payment.view' => 'View customer payments',
                'collections.payment.collect' => 'Collect payments',
                'collections.payment.approve' => 'Post payments',
                'collections.payment.reverse' => 'Reverse payments',
                'collections.payment.receipt.view' => 'View money receipts',
                'collections.payment.statement.export' => 'Export customer statements',
                'collections.latefee.view' => 'View late fees',
                'collections.latefee.manage' => 'Manage late-fee policies and assessments',
                'collections.latefee.waive' => 'Waive late fees',
            ],
            'Investors' => [
                'investor.view' => 'View investors',
                'investor.create' => 'Create investors',
                'investor.edit' => 'Edit investors',
                'investor.ledger.view' => 'View investor ledger',
                'investor.ledger.export' => 'Export investor ledger',
                'investor.agreement.create' => 'Create investment agreements',
                'investor.agreement.approve' => 'Approve investment agreements',
                'investor.agreement.edit' => 'Edit draft agreements',
                'investor.agreement.close' => 'Close agreements',
                'investor.capital.record' => 'Record capital transactions',
                'investor.capital.approve' => 'Post capital transactions',
                'investor.capital.return' => 'Return capital',
                'investor.cost.view' => 'View flat cost',
                'investor.cost.allocate' => 'Run cost allocation',
                'investor.cost.approve' => 'Approve cost allocation',
                'investor.profit.calculate' => 'Calculate flat profit',
                'investor.profit.submit' => 'Submit profit calculations',
                'investor.profit.approve' => 'Approve flat profit',
                'investor.profit.distribution.create' => 'Request profit distribution',
                'investor.profit.distribution.approve' => 'Approve profit distribution',
                'investor.profit.payout.post' => 'Post profit payouts',
                'investor.profit.reverse' => 'Reverse payouts',
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
            'Reports' => [
                'dashboard.executive.view' => 'View executive dashboard',
                'reports.view' => 'Open reports',
                'reports.export' => 'Export reports',
                'reports.project' => 'Project reports',
                'reports.construction' => 'Construction reports',
                'reports.inventory' => 'Inventory reports',
                'reports.purchase' => 'Purchasing reports',
                'reports.contractor' => 'Contractor reports',
                'reports.expense' => 'Expense reports',
                'reports.sales' => 'Sales reports',
                'reports.collections' => 'Collection reports',
                'reports.investors' => 'Investor reports',
                'reports.audit' => 'Audit reports',
            ],
            'Accounting' => [
                'accounting.dashboard.view' => 'View accounting overview',
                'accounting.coa.view' => 'View chart of accounts',
                'accounting.coa.manage' => 'Manage chart of accounts',
                'accounting.journal.view' => 'View journals',
                'accounting.journal.create' => 'Create and submit journals',
                'accounting.journal.approve' => 'Approve journals',
                'accounting.journal.post' => 'Post journals',
                'accounting.journal.reverse' => 'Reverse journals',
                'accounting.cashbank.view' => 'View cash and bank accounts',
                'accounting.cashbank.manage' => 'Manage accounts, transfers, money in and out',
                'accounting.cheque.manage' => 'Update cheque status',
                'accounting.bankreconciliation.manage' => 'Reconcile bank statements',
                'accounting.ledger.view' => 'View general ledger',
                'accounting.reports.view' => 'View financial statements',
                'accounting.reports.export' => 'Export financial statements',
                'accounting.period.lock' => 'Lock and unlock periods',
                'accounting.period.close' => 'Close and reopen fiscal years',
                'accounting.openingbalance.manage' => 'Enter opening balances',
                'accounting.reconciliation.view' => 'View reconciliation centre',
                'accounting.reconciliation.run' => 'Retry and resolve postings',
                'accounting.settings.manage' => 'Accounting settings and account mapping',
            ],
            'Portal Access' => [
                'portal.links.manage' => 'Manage portal accounts and links',
                'portal.grants.manage' => 'Manage investor portal grants',
            ],
            'Investor Portal' => [
                'investor.portal.view' => 'Use the investor portal',
                'investor.portal.investments.view' => 'See own investments',
                'investor.portal.project.progress.view' => 'See project progress',
                'investor.portal.project.financials.view' => 'See project financials',
                'investor.portal.flat.financials.view' => 'See unit financials',
                'investor.portal.cost.material.view' => 'See material cost',
                'investor.portal.cost.labour.view' => 'See labour cost',
                'investor.portal.cost.contractor.view' => 'See contractor cost',
                'investor.portal.cost.expense.view' => 'See project expenses',
                'investor.portal.profit.view' => 'See profit',
                'investor.portal.documents.download' => 'Download documents',
                'investor.portal.statements.export' => 'Export statements',
            ],
            'Customer Portal' => [
                'customer.portal.view' => 'Use the customer portal',
                'customer.portal.flats.view' => 'See own units',
                'customer.portal.installments.view' => 'See installments',
                'customer.portal.payments.view' => 'See payments',
                'customer.portal.statements.download' => 'Download receipts and statements',
                'customer.portal.project.progress.view' => 'See project progress',
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
            'Admin' => array_values(array_filter($all, fn ($p) => ! str_starts_with($p, 'investor.portal.') && ! str_starts_with($p, 'customer.portal.'))),
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
                'sales.booking.view', 'sales.booking.create', 'sales.booking.edit', 'sales.booking.submit', 'sales.booking.cancel.request',
                'sales.sale.view', 'sales.price.view', 'sales.price.history.view', 'collections.plan.view', 'collections.payment.view', 'collections.payment.collect',
                'collections.payment.receipt.view',
                'approvals.request',
            ],
            'Accountant' => array_merge(
                ['dashboard.view', 'dashboard.executive.view', 'reports.view', 'reports.export', 'projects.view_all', 'flats.view', 'customers.view_all'],
                array_values(array_filter($all, fn ($p) => str_starts_with($p, 'accounting.'))),
            ),
            'Accounts Viewer' => [
                'dashboard.view', 'accounting.dashboard.view', 'accounting.coa.view', 'accounting.journal.view', 'accounting.cashbank.view',
                'accounting.ledger.view', 'accounting.reports.view', 'accounting.reconciliation.view',
            ],
            'Viewer' => [
                'dashboard.view', 'projects.view', 'flats.view', 'customers.view',
            ],
            'Investor' => [
                'investor.portal.view', 'investor.portal.investments.view', 'investor.portal.project.progress.view', 'investor.portal.flat.financials.view',
                'investor.portal.profit.view', 'investor.portal.documents.download', 'investor.portal.statements.export',
            ],
            'Customer' => [
                'customer.portal.view', 'customer.portal.flats.view', 'customer.portal.installments.view', 'customer.portal.payments.view', 'customer.portal.statements.download',
            ],
        ];
    }
}
