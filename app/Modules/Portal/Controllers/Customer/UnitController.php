<?php

namespace App\Modules\Portal\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\FlatSale;
use App\Modules\Portal\Services\CustomerPortal;
use App\Modules\Portal\Services\PortalAccess;
use App\Modules\Portal\Services\PortalContext;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(PortalContext $context): View
    {
        abort_unless($context->allows('customer.portal.flats.view'), 403);
        $portal = CustomerPortal::for($context);

        return view('portal.customer.units.index', [
            'units' => $portal->sales()->latest('agreement_date')->get()->concat($portal->bookings()->get())->map(fn ($u) => ['unit' => $u, 'position' => $portal->position($u)]),
        ]);
    }

    public function show(PortalContext $context, string $unit): View
    {
        abort_unless($context->allows('customer.portal.flats.view'), 403);
        $portal = CustomerPortal::for($context);
        $record = $portal->unit($unit);

        return view('portal.customer.units.show', [
            'unit' => $record,
            'isSale' => $record instanceof FlatSale,
            'position' => $portal->position($record),
            'canInstallments' => $context->allows('customer.portal.installments.view'),
            'progressProject' => PortalAccess::customerProgressProjectIds($context->user, $context->customer)->contains($record->project_id)
                ? $record->project->loadMissing('stages') : null,
        ]);
    }
}
