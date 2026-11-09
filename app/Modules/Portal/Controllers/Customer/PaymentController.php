<?php

namespace App\Modules\Portal\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\FlatSale;
use App\Modules\Portal\Services\CustomerPortal;
use App\Modules\Portal\Services\PortalAccess;
use App\Modules\Portal\Services\PortalContext;
use App\Support\Ajax;
use App\Support\Export\Documents;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function index(PortalContext $context): View
    {
        abort_unless($context->allows('customer.portal.payments.view'), 403);
        $portal = CustomerPortal::for($context);

        return view('portal.customer.payments', [
            'units' => $portal->sales()->get()->concat($portal->bookings()->get())->mapWithKeys(fn ($u) => [$u->public_id => $u->project->name.' · Unit '.$u->flat->flat_no]),
            'refunds' => $portal->refunds(),
        ]);
    }

    public function table(Request $request, PortalContext $context): JsonResponse
    {
        abort_unless($context->allows('customer.portal.payments.view'), 403);
        $portal = CustomerPortal::for($context);
        $query = $portal->payments();
        if ($request->filled('unit')) {
            $unit = $portal->unit((string) $request->input('unit'));
            $query->where($unit instanceof FlatSale ? 'sale_id' : 'booking_id', $unit->id);
        }
        $rows = TableQuery::apply($query, $request, ['payment_no', 'reference_no'], ['date' => 'payment_date', 'amount' => 'amount'], 'date', 'desc');

        return Ajax::table(view('portal.customer._payment_rows', ['payments' => $rows, 'canReceipts' => $context->allows('customer.portal.statements.download')])->render(), $rows);
    }

    public function receipt(Request $request, PortalContext $context, string $payment): Response
    {
        abort_unless($context->allows('customer.portal.statements.download'), 403);
        $record = CustomerPortal::for($context)->payment($payment);
        abort_unless($record->receipt, 404);
        PortalAccess::audit($context->user, 'customer', 'download', $record, 'ok', $request);

        return Documents::moneyReceipt($record)->respond($request);
    }
}
