<?php

namespace App\Modules\Investors\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Flat;
use App\Models\Investor;
use App\Models\InvestorLedgerEntry;
use App\Models\InvestorPayout;
use App\Models\InvestorProfitDistribution;
use App\Modules\Investors\Services\DistributionService;
use App\Modules\Investors\Services\InvestorLedger;
use App\Support\Ajax;
use App\Support\Export\Documents;
use App\Support\Export\Report;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DistributionController extends Controller
{
    public function __construct(private DistributionService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('investor.view'), 403);

        return view('investors.distributions.index');
    }

    /** Profit payable and paid per investor and unit. */
    public function payables(Request $request): JsonResponse
    {
        $rows = TableQuery::paginate($this->payableQuery($request), $request);
        $investors = Investor::query()->whereIn('id', $rows->pluck('investor_id'))->get()->keyBy('id');
        $flats = Flat::query()->whereIn('id', $rows->pluck('flat_id'))->with(['project:id,public_id,code', 'building:id,code'])->get()->keyBy('id');
        $rows->getCollection()->each(function ($r) use ($investors, $flats) {
            $r->investor = $investors[$r->investor_id];
            $r->flat = $flats[$r->flat_id];
            $r->available = DistributionService::available($r->investor, $r->flat_id);
        });

        return Ajax::table(view('investors.distributions._payables', ['rows' => $rows])->render(), $rows);
    }

    public function table(Request $request): JsonResponse
    {
        $rows = TableQuery::paginate($this->listing($request), $request);

        return Ajax::table(view('investors.distributions._rows', ['distributions' => $rows])->render(), $rows);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('investor.ledger.export'), 403);

        return Report::make('Profit Distributions')->meta(['Status' => InvestorProfitDistribution::STATUSES[(string) $request->input('status')] ?? null])
            ->columns(['Distribution No' => 'text', 'Date' => 'date', 'Investor' => 'text', 'Project' => 'text', 'Unit' => 'text', 'Amount' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (InvestorProfitDistribution $d) => [$d->distribution_no, $d->created_at, $d->investor->name, $d->flat->project->code, $d->flat->flat_no, $d->amount, InvestorProfitDistribution::STATUSES[$d->status]]))
            ->respond($request);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('investor.profit.distribution.create'), 403);
        $investor = Investor::findByPublicId($request->input('investor')) ?? abort(404);
        $flat = Flat::query()->visibleTo($request->user())->where('public_id', (string) $request->input('flat'))->with(['project', 'building'])->first() ?? abort(404);

        return Ajax::success(null, ['title' => 'Distribute Profit', 'html' => view('investors.distributions._form', [
            'investor' => $investor, 'flat' => $flat, 'balances' => InvestorLedger::balances($investor, $flat->id),
            'available' => DistributionService::available($investor, $flat->id), 'key' => (string) Str::uuid(),
        ])->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('investor.profit.distribution.create'), 403);
        $request->merge(['amount' => str_replace(',', '', (string) $request->input('amount'))]);
        $data = $request->validate([
            'investor' => ['required', 'string', 'size:16'],
            'flat' => ['required', 'string', 'size:16'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $investor = Investor::findByPublicId($data['investor']) ?? throw ValidationException::withMessages(['investor' => 'Select an investor.']);
        $flat = Flat::query()->visibleTo($request->user())->where('public_id', $data['flat'])->first() ?? throw ValidationException::withMessages(['flat' => 'Select a unit you can access.']);
        $d = $this->service->request($investor, $flat, (string) $data['amount'], $request->user(), $data['idempotency_key']);

        return Ajax::success('Distribution '.$d->distribution_no.' requested.', ['redirect' => route('distributions.show', $d)]);
    }

    public function show(Request $request, InvestorProfitDistribution $distribution): View
    {
        $this->authorizeDistribution($request, $distribution, 'investor.view');
        $distribution->load(['investor', 'flat.project', 'flat.building', 'requester', 'approver', 'payouts.poster']);

        return view('investors.distributions.show', [
            'd' => $distribution,
            'balances' => InvestorLedger::balances($distribution->investor, $distribution->flat_id),
            'available' => DistributionService::available($distribution->investor, $distribution->flat_id, $distribution->id),
        ]);
    }

    public function approve(Request $request, InvestorProfitDistribution $distribution): JsonResponse
    {
        $this->authorizeDistribution($request, $distribution, 'investor.profit.distribution.approve');
        try {
            $this->service->approve($distribution, $request->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['distribution' => collect($e->errors())->flatten()->first()]);
        }

        return Ajax::success('Distribution approved.');
    }

    public function rejectForm(Request $request, InvestorProfitDistribution $distribution): JsonResponse
    {
        $this->authorizeDistribution($request, $distribution, 'investor.profit.distribution.approve');

        return Ajax::success(null, ['title' => 'Reject '.$distribution->distribution_no, 'html' => view('partials.reason-form', [
            'action' => route('distributions.reject', $distribution), 'button' => 'Reject', 'reload' => true,
        ])->render()]);
    }

    public function reject(Request $request, InvestorProfitDistribution $distribution): JsonResponse
    {
        $this->authorizeDistribution($request, $distribution, 'investor.profit.distribution.approve');
        $this->service->reject($distribution, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Distribution rejected.');
    }

    public function payForm(Request $request, InvestorProfitDistribution $distribution): JsonResponse
    {
        $this->authorizeDistribution($request, $distribution, 'investor.profit.payout.post');

        return Ajax::success(null, ['title' => 'Pay '.$distribution->distribution_no, 'html' => view('investors.distributions._pay', ['d' => $distribution, 'key' => (string) Str::uuid()])->render()]);
    }

    public function pay(Request $request, InvestorProfitDistribution $distribution): JsonResponse
    {
        $this->authorizeDistribution($request, $distribution, 'investor.profit.payout.post');
        $data = $request->validate([
            'paid_at' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'payment_method' => ['required', Rule::in(array_keys(CapitalController::METHODS))],
            'payment_reference' => [Rule::requiredIf(in_array($request->input('payment_method'), ['bank', 'cheque', 'mobile'], true)), 'nullable', 'string', 'max:80'],
            'idempotency_key' => ['required', 'uuid'],
        ], ['payment_reference.required' => 'Enter the transaction or cheque reference.']);
        $payout = $this->service->pay($distribution, $data, $request->user(), $data['idempotency_key']);

        return Ajax::success('Payout '.$payout->payout_no.' posted.');
    }

    public function reverseForm(Request $request, InvestorPayout $payout): JsonResponse
    {
        $this->authorizeDistribution($request, $payout->distribution, 'investor.profit.reverse');

        return Ajax::success(null, ['title' => 'Reverse '.$payout->payout_no, 'html' => view('partials.reason-form', [
            'action' => route('payouts.reverse', $payout), 'button' => 'Reverse', 'reload' => true,
        ])->render()]);
    }

    public function reverse(Request $request, InvestorPayout $payout): JsonResponse
    {
        $this->authorizeDistribution($request, $payout->distribution, 'investor.profit.reverse');
        $this->service->reversePayout($payout, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Payout reversed.');
    }

    public function voucher(Request $request, InvestorPayout $payout): Response
    {
        $this->authorizeDistribution($request, $payout->distribution, 'investor.view');

        return Documents::investorPayout($payout)
            ->respond($request);
    }

    private function authorizeDistribution(Request $request, InvestorProfitDistribution $distribution, string $permission): void
    {
        abort_unless($request->user()->can($permission) && $distribution->isVisibleTo($request->user()), 403);
    }

    private function payableQuery(Request $request): Builder
    {
        abort_unless($request->user()->can('investor.view'), 403);
        $kind = 'COALESCE(o.entry_kind, e.entry_kind)';
        $query = InvestorLedgerEntry::query()->from('investor_ledger_entries as e')->leftJoin('investor_ledger_entries as o', 'o.id', '=', 'e.reversal_of_id')
            ->whereIn('e.flat_id', Flat::query()->visibleTo($request->user())->select('flats.id'))
            ->select('e.investor_id', 'e.flat_id')
            ->selectRaw("SUM(CASE WHEN {$kind} = 'profit_payable' THEN e.amount ELSE 0 END) AS payable, SUM(CASE WHEN {$kind} = 'profit_paid' THEN e.amount ELSE 0 END) AS paid")
            ->groupBy('e.investor_id', 'e.flat_id')
            ->havingRaw("SUM(CASE WHEN {$kind} = 'profit_payable' THEN e.amount ELSE 0 END) <> 0")
            ->orderByRaw("SUM(CASE WHEN {$kind} = 'profit_payable' THEN e.amount WHEN {$kind} = 'profit_paid' THEN -e.amount ELSE 0 END) DESC");
        if ($investor = Investor::findByPublicId($request->input('investor'))) {
            $query->where('e.investor_id', $investor->id);
        }

        return $query;
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('investor.view'), 403);
        $query = InvestorProfitDistribution::query()->visibleTo($request->user())->with(['investor:id,public_id,name,investor_code', 'flat:id,public_id,flat_no,project_id', 'flat.project:id,public_id,code']);
        if (array_key_exists($status = (string) $request->input('status'), InvestorProfitDistribution::STATUSES)) {
            $query->where('status', $status);
        }
        if ($investor = Investor::findByPublicId($request->input('investor'))) {
            $query->where('investor_id', $investor->id);
        }

        return TableQuery::prepare($query, $request, ['distribution_no', 'investor.name'], ['date' => 'created_at', 'amount' => 'amount'], 'date', 'desc');
    }
}
