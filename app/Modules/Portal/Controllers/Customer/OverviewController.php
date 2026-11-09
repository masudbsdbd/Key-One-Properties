<?php

namespace App\Modules\Portal\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\Portal\Services\CustomerPortal;
use App\Modules\Portal\Services\PortalContext;
use App\Support\Money;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function __invoke(PortalContext $context): View
    {
        $portal = CustomerPortal::for($context);
        $units = $portal->sales(true)->get()->concat($portal->bookings()->get())
            ->map(fn ($u) => ['unit' => $u, 'position' => $portal->position($u)]);
        $sum = fn (string $key) => Money::amount(Money::add('0', ...$units->pluck('position.'.$key)->all()));
        $next = $units->map(fn ($u) => $u['position']['next'] ? ['item' => $u['position']['next'], 'unit' => $u['unit']] : null)->filter()->sortBy(fn ($n) => $n['item']->due_date)->first();

        return view('portal.customer.overview', [
            'context' => $context,
            'units' => $units,
            'totals' => ['contract' => $sum('contract'), 'paid' => $sum('paid'), 'balance' => $sum('balance'), 'overdue' => $sum('overdue')],
            'next' => $next,
            'recent' => $context->allows('customer.portal.payments.view') ? $portal->payments()->where('status', 'posted')->latest('payment_date')->latest('id')->take(5)->get() : collect(),
        ]);
    }
}
