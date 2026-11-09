<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomerPayment;
use App\Models\CustomerStatementEntry;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\InstallmentScheduleItem;
use App\Models\Project;
use App\Models\User;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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
            'summary' => $this->summary($user),
        ]);
    }

    public function summaryExport(Request $request): Response
    {
        abort_unless($request->user()->canAny(['sales.booking.view', 'sales.sale.view']) && $request->user()->can('sales.export'), 403);
        $rows = $this->summary($request->user());

        return Report::make('Sales and Collection Summary')
            ->columns(['Project' => 'text', 'Units' => 'number', 'Available' => 'number', 'Booked' => 'number', 'Sold' => 'number', 'Sales Value' => 'money', 'Collected' => 'money', 'Receivable' => 'money', 'Overdue' => 'money'])
            ->rows(fn () => $rows->map(fn ($r) => [$r['project']->code.' · '.$r['project']->name, $r['units'], $r['available'], $r['booked'], $r['sold'], $r['value'], $r['collected'], $r['receivable'], $r['overdue']]))
            ->totals(['Total', $rows->sum('units'), $rows->sum('available'), $rows->sum('booked'), $rows->sum('sold'),
                Money::amount(Money::add('0', ...$rows->pluck('value')->all())), Money::amount(Money::add('0', ...$rows->pluck('collected')->all())),
                Money::amount(Money::add('0', ...$rows->pluck('receivable')->all())), Money::amount(Money::add('0', ...$rows->pluck('overdue')->all()))])
            ->respond($request);
    }

    /** Units, sales value, collection and overdue per visible project. */
    private function summary(User $user): Collection
    {
        $projects = Project::query()->visibleTo($user)->orderBy('code')->get(['id', 'public_id', 'code', 'name']);
        $ids = $projects->pluck('id');
        $units = Flat::query()->whereIn('project_id', $ids)->selectRaw("project_id, COUNT(*) AS units, SUM(status = 'available') AS available, SUM(status = 'booked') AS booked, SUM(status = 'sold') AS sold")
            ->groupBy('project_id')->get()->keyBy('project_id');
        $value = FlatSale::query()->whereIn('project_id', $ids)->whereIn('status', FlatSale::ACTIVE)->selectRaw('project_id, SUM(net_contract_value) AS v')->groupBy('project_id')->pluck('v', 'project_id');
        $collected = CustomerPayment::query()->whereIn('project_id', $ids)->where('status', 'posted')->selectRaw('project_id, SUM(amount) AS v')->groupBy('project_id')->pluck('v', 'project_id');
        $receivable = CustomerStatementEntry::query()->whereIn('sale_id', FlatSale::query()->whereIn('project_id', $ids)->whereIn('status', FlatSale::ACTIVE)->select('id'))
            ->join('flat_sales', 'flat_sales.id', '=', 'customer_statement_entries.sale_id')
            ->selectRaw('flat_sales.project_id, SUM(charge_amount - credit_amount) AS v')->groupBy('flat_sales.project_id')->pluck('v', 'project_id');
        $overdue = InstallmentScheduleItem::query()->join('installment_plans', 'installment_plans.id', '=', 'installment_schedule_items.plan_id')
            ->join('flat_sales', 'flat_sales.id', '=', 'installment_plans.sale_id')->whereIn('flat_sales.project_id', $ids)
            ->where('installment_plans.status', 'active')->where('installment_schedule_items.status', '!=', 'rescheduled')
            ->whereDate('installment_schedule_items.due_date', '<', now(config('app.display_timezone'))->toDateString())
            ->selectRaw('flat_sales.project_id, SUM(principal_due + fee_due - principal_paid - fee_paid) AS v')->groupBy('flat_sales.project_id')->pluck('v', 'project_id');

        return $projects->map(fn (Project $p) => [
            'project' => $p,
            'units' => (int) ($units[$p->id]->units ?? 0), 'available' => (int) ($units[$p->id]->available ?? 0),
            'booked' => (int) ($units[$p->id]->booked ?? 0), 'sold' => (int) ($units[$p->id]->sold ?? 0),
            'value' => Money::amount((string) ($value[$p->id] ?? 0)), 'collected' => Money::amount((string) ($collected[$p->id] ?? 0)),
            'receivable' => Money::amount((string) ($receivable[$p->id] ?? 0)), 'overdue' => Money::amount((string) ($overdue[$p->id] ?? 0)),
        ])->values();
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
