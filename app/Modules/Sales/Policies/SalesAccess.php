<?php

namespace App\Modules\Sales\Policies;

use App\Models\CustomerRefund;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\User;
use App\Support\Attachments\AttachableRegistry;
use Illuminate\Support\Facades\Gate;

class SalesAccess
{
    public static function register(): void
    {
        Gate::define('booking:files-view', fn (User $u, FlatBooking $b) => $u->can('sales.booking.view') && $b->isVisibleTo($u));
        Gate::define('booking:files-manage', fn (User $u, FlatBooking $b) => $u->can('sales.booking.edit') && $b->isVisibleTo($u));
        Gate::define('sale:files-view', fn (User $u, FlatSale $s) => $u->can('sales.sale.view') && $s->isVisibleTo($u));
        Gate::define('sale:files-manage', fn (User $u, FlatSale $s) => $u->canAny(['sales.sale.agreement.manage', 'sales.sale.registration.manage', 'sales.sale.handover.manage']) && $s->isVisibleTo($u));
        Gate::define('refund:files-view', fn (User $u, CustomerRefund $r) => $u->can('sales.refund.view') && $r->isVisibleTo($u));
        Gate::define('refund:files-manage', fn (User $u, CustomerRefund $r) => $u->canAny(['sales.refund.request', 'sales.refund.pay']) && $r->isVisibleTo($u));

        AttachableRegistry::register('booking', FlatBooking::class, 'booking:files-view', 'booking:files-manage');
        AttachableRegistry::register('sale', FlatSale::class, 'sale:files-view', 'sale:files-manage');
        AttachableRegistry::register('refund', CustomerRefund::class, 'refund:files-view', 'refund:files-manage');
    }
}
