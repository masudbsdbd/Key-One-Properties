<?php

namespace App\Modules\Purchasing\Policies;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\Attachments\AttachableRegistry;
use Illuminate\Support\Facades\Gate;

class PurchasingAccess
{
    public static function register(): void
    {
        Gate::define('po:view', fn (User $u, PurchaseOrder $po) => $u->can('purchases.view') && $po->isVisibleTo($u));
        Gate::define('po:edit', fn (User $u, PurchaseOrder $po) => $u->can('purchases.create') && $po->isVisibleTo($u));
        Gate::define('po:approve', fn (User $u, PurchaseOrder $po) => $u->can('purchases.approve') && $po->isVisibleTo($u));
        Gate::define('po:receive', fn (User $u, PurchaseOrder $po) => $u->can('purchases.receive') && $po->isVisibleTo($u) && $po->warehouse->isVisibleTo($u));
        Gate::define('po:return', fn (User $u, PurchaseOrder $po) => $u->can('purchases.return') && $po->isVisibleTo($u));

        Gate::define('supplier:files-view', fn (User $u, Supplier $s) => $u->can('suppliers.view'));
        Gate::define('supplier:files-manage', fn (User $u, Supplier $s) => $u->can('suppliers.update'));
        Gate::define('po:files-view', fn (User $u, PurchaseOrder $po) => Gate::forUser($u)->allows('po:view', $po));
        Gate::define('po:files-manage', fn (User $u, PurchaseOrder $po) => Gate::forUser($u)->allows('po:edit', $po));
        Gate::define('bill:files-view', fn (User $u, SupplierBill $b) => $u->can('purchases.view') && $b->isVisibleTo($u));
        Gate::define('bill:files-manage', fn (User $u, SupplierBill $b) => $u->can('purchases.bill.manage') && $b->isVisibleTo($u));

        AttachableRegistry::register('supplier', Supplier::class, 'supplier:files-view', 'supplier:files-manage');
        AttachableRegistry::register('purchase-order', PurchaseOrder::class, 'po:files-view', 'po:files-manage');
        Gate::define('payment:files-view', fn (User $u, SupplierPayment $p) => $u->can('purchases.view') && $p->isVisibleTo($u));
        Gate::define('payment:files-manage', fn (User $u, SupplierPayment $p) => $u->can('purchases.payment.create') && $p->isVisibleTo($u));
        AttachableRegistry::register('supplier-payment', SupplierPayment::class, 'payment:files-view', 'payment:files-manage');
        AttachableRegistry::register('supplier-bill', SupplierBill::class, 'bill:files-view', 'bill:files-manage');
    }
}
