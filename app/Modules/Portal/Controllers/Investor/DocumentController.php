<?php

namespace App\Modules\Portal\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorPayout;
use App\Modules\Portal\Services\InvestorPortal;
use App\Modules\Portal\Services\PortalAccess;
use App\Modules\Portal\Services\PortalContext;
use App\Support\Export\Documents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Documents of the signed-in investor only; anything else answers 404. */
class DocumentController extends Controller
{
    public function index(PortalContext $context): View
    {
        $this->authorizeDownloads($context);
        $portal = new InvestorPortal($context);

        return view('portal.investor.documents', [
            'agreements' => $portal->agreements()->with(['flat:id,flat_no', 'project:id,code,name'])->orderByDesc('effective_from')->get(),
            'capital' => $portal->capitalTransactions()->where('status', '!=', 'pending'),
            'payouts' => $portal->payouts(),
            'files' => $portal->sharedFiles(),
        ]);
    }

    public function agreement(Request $request, PortalContext $context, string $agreement): Response
    {
        $this->authorizeDownloads($context);
        $record = (new InvestorPortal($context))->agreement($agreement);
        PortalAccess::audit($context->user, 'investor', 'download', $record, 'ok', $request);

        return Documents::investorAgreement($record)->respond($request);
    }

    public function capital(Request $request, PortalContext $context, string $transaction): Response
    {
        $this->authorizeDownloads($context);
        $record = InvestorCapitalTransaction::query()->where('investor_id', $context->investor->id)->whereIn('status', ['posted', 'reversed'])->where('public_id', $transaction)->first() ?? abort(404);
        PortalAccess::audit($context->user, 'investor', 'download', $record, 'ok', $request);

        return Documents::capitalTransaction($record)->respond($request);
    }

    public function payout(Request $request, PortalContext $context, string $payout): Response
    {
        $this->authorizeDownloads($context);
        $record = InvestorPayout::query()->where('investor_id', $context->investor->id)->where('public_id', $payout)->first() ?? abort(404);
        PortalAccess::audit($context->user, 'investor', 'download', $record, 'ok', $request);

        return Documents::investorPayout($record)->respond($request);
    }

    public function file(Request $request, PortalContext $context, string $file): Response
    {
        $this->authorizeDownloads($context);
        $record = (new InvestorPortal($context))->sharedFile($file);
        PortalAccess::audit($context->user, 'investor', 'download', $record, 'ok', $request);

        return Storage::disk($record->disk)->download($record->path, $record->original_name, ['Cache-Control' => 'private, no-store']);
    }

    private function authorizeDownloads(PortalContext $context): void
    {
        abort_unless($context->allows('investor.portal.documents.download'), 403);
    }
}
