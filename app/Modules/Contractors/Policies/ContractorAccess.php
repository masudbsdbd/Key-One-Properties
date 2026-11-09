<?php

namespace App\Modules\Contractors\Policies;

use App\Models\Contractor;
use App\Models\ContractorBill;
use App\Models\ContractorPayment;
use App\Models\ContractorWorkOrder;
use App\Models\User;
use App\Support\Attachments\AttachableRegistry;
use Illuminate\Support\Facades\Gate;

/**
 * Work orders, bills and payments follow project visibility; the directory needs contractors.view.
 */
class ContractorAccess
{
    public static function register(): void
    {
        $sees = fn (User $u, $doc) => $u->can('contractors.view') && $doc->project->isVisibleTo($u);

        Gate::define('wo:view', fn (User $u, ContractorWorkOrder $wo) => $sees($u, $wo));
        foreach (['workorder.create' => 'wo:edit', 'workorder.approve' => 'wo:approve', 'measurement.manage' => 'wo:measure',
            'measurement.approve' => 'wo:measure-approve', 'bill.create' => 'wo:bill', 'payment.create' => 'wo:pay'] as $permission => $ability) {
            Gate::define($ability, fn (User $u, ContractorWorkOrder $wo) => $u->can('contractors.'.$permission) && $sees($u, $wo));
        }
        Gate::define('cbill:view', fn (User $u, ContractorBill $b) => $sees($u, $b));
        Gate::define('cbill:approve', fn (User $u, ContractorBill $b) => $u->can('contractors.bill.approve') && $sees($u, $b));
        Gate::define('cbill:manage', fn (User $u, ContractorBill $b) => $u->can('contractors.bill.create') && $sees($u, $b));
        Gate::define('cpay:view', fn (User $u, ContractorPayment $p) => $sees($u, $p));
        Gate::define('cpay:approve', fn (User $u, ContractorPayment $p) => $u->can('contractors.payment.approve') && $sees($u, $p));
        Gate::define('cpay:manage', fn (User $u, ContractorPayment $p) => $u->can('contractors.payment.create') && $sees($u, $p));

        Gate::define('contractor:files-view', fn (User $u, Contractor $c) => $u->can('contractors.view'));
        Gate::define('contractor:files-manage', fn (User $u, Contractor $c) => $u->can('contractors.update'));
        AttachableRegistry::register('contractor', Contractor::class, 'contractor:files-view', 'contractor:files-manage');
        AttachableRegistry::register('work-order', ContractorWorkOrder::class, 'wo:view', 'wo:edit');
        AttachableRegistry::register('contractor-bill', ContractorBill::class, 'cbill:view', 'cbill:manage');
        AttachableRegistry::register('contractor-payment', ContractorPayment::class, 'cpay:view', 'cpay:manage');
    }
}
