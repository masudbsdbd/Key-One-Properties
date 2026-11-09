<?php

namespace App\Modules\Portal\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\Portal\Services\CustomerPortal;
use App\Modules\Portal\Services\PortalContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstallmentController extends Controller
{
    public function index(Request $request, PortalContext $context): View
    {
        abort_unless($context->allows('customer.portal.installments.view'), 403);
        $portal = CustomerPortal::for($context);
        $sales = $portal->sales(true)->withExists(['plans as has_plan' => fn ($q) => $q->where('status', 'active')])->orderBy('agreement_date')->orderBy('id')->get();
        $sale = $request->filled('unit')
            ? $sales->firstWhere('public_id', (string) $request->input('unit')) ?? abort(404)
            : ($sales->firstWhere('has_plan', true) ?? $sales->first());

        return view('portal.customer.installments', [
            'sales' => $sales,
            'sale' => $sale,
            'position' => $sale ? $portal->position($sale) : null,
            'reschedules' => $sale ? $portal->reschedules($sale) : collect(),
            'fees' => $sale ? $portal->lateFees($sale) : collect(),
        ]);
    }
}
