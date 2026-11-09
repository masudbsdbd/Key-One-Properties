<?php

namespace App\Modules\Portal\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Modules\Portal\Services\PortalContext;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function __invoke(PortalContext $context): View
    {
        return view('portal.investor.overview', ['context' => $context]);
    }
}
