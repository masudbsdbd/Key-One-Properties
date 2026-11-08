<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomerPayment;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\Project;
use App\Support\Ajax;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesOverviewController extends Controller
{
    public function overview(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->canAny(['sales.booking.view', 'sales.sale.view']), 403);
        $flats = Flat::query()->visibleTo($user);
        $month = now(config('app.display_timezone'));
        $sales = FlatSale::query()->visibleTo($user);

        return view('sales.overview', [
            'counts' => (clone $flats)->selectRaw('status, COUNT(*) AS c')->groupBy('status')->pluck('c', 'status'),
            'pendingBookings' => FlatBooking::query()->visibleTo($user)->where('status', 'pending')->count(),
            'pendingSales' => (clone $sales)->where('status', 'pending')->count(),
            'salesValue' => Money::amount((string) (clone $sales)->whereIn('status', FlatSale::ACTIVE)->sum('net_contract_value')),
            'monthSales' => Money::amount((string) (clone $sales)->whereIn('status', FlatSale::ACTIVE)->whereBetween('agreement_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->sum('net_contract_value')),
            'collected' => Money::amount((string) CustomerPayment::query()->visibleTo($user)->where('status', 'posted')->sum('amount')),
            'recentBookings' => FlatBooking::query()->visibleTo($user)->with(['flat', 'customer', 'project'])->latest('id')->limit(6)->get(),
            'recentSales' => (clone $sales)->with(['flat', 'customer', 'project'])->latest('id')->limit(6)->get(),
        ]);
    }

    public function availability(Request $request): View
    {
        abort_unless($request->user()->canAny(['sales.booking.view', 'sales.sale.view']), 403);
        $project = Project::query()->visibleTo($request->user())->where('public_id', (string) $request->input('project'))->first()
            ?? Project::query()->visibleTo($request->user())->whereHas('flats')->orderBy('name')->first();

        return view('sales.availability', ['project' => $project]);
    }

    public function board(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['sales.booking.view', 'sales.sale.view']), 403);
        $project = Project::query()->visibleTo($request->user())->where('public_id', (string) $request->input('project'))->firstOrFail();
        $buildings = $project->buildings()->with(['floors' => fn ($q) => $q->orderByDesc('floor_number'), 'floors.flats' => fn ($q) => $q->orderBy('flat_no')->with('claim.claimant')])->orderBy('code')->get();

        return Ajax::success(null, ['html' => view('sales._board', ['project' => $project, 'buildings' => $buildings])->render()]);
    }
}
