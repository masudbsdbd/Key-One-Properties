<?php

namespace App\Modules\Investors\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Flat;
use App\Models\Investor;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorFlatAgreement;
use App\Models\InvestorLedgerEntry;
use App\Modules\Investors\Services\InvestorService;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\Money;
use App\Support\Phone;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

class InvestorController extends Controller
{
    public function __construct(private InvestorService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('investor.view'), 403);

        return view('investors.index');
    }

    public function table(Request $request): JsonResponse
    {
        $investors = TableQuery::paginate($this->listing($request), $request);

        return Ajax::table(view('investors._rows', ['investors' => $investors])->render(), $investors);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('investor.ledger.export'), 403);

        return Report::make('Investors')->meta(['Status' => Investor::STATUSES[(string) $request->input('status')] ?? null])
            ->columns(['Code' => 'text', 'Name' => 'text', 'Mobile' => 'text', 'Email' => 'text', 'Active Units' => 'number', 'Capital' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (Investor $i) => [$i->investor_code, $i->name, Phone::display($i->mobile), $i->email, $i->active_agreements, $i->capital ?? '0', Investor::STATUSES[$i->status]]))
            ->respond($request);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('investor.view'), 403);
        $query = Investor::query()->when($request->boolean('active'), fn ($q) => $q->where('status', 'active'))->orderBy('name');
        [$results, $more] = TableQuery::options($query, $request, ['name', 'investor_code', 'mobile'], fn (Investor $i) => ['id' => $i->public_id, 'text' => $i->name.' · '.$i->investor_code]);

        return Ajax::options($results, $more);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('investor.create'), 403);

        return $this->form(new Investor(['status' => 'active']), 'New Investor');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('investor.create'), 403);
        $investor = $this->service->saveInvestor(null, $this->input($request), $request->user());

        return Ajax::success('Investor '.$investor->investor_code.' added.', ['redirect' => route('investors.show', $investor)]);
    }

    public function edit(Request $request, Investor $investor): JsonResponse
    {
        abort_unless($request->user()->can('investor.edit'), 403);

        return $this->form($investor, 'Edit '.$investor->investor_code);
    }

    public function update(Request $request, Investor $investor): JsonResponse
    {
        abort_unless($request->user()->can('investor.edit'), 403);
        $this->service->saveInvestor($investor, $this->input($request), $request->user());

        return Ajax::success('Investor updated.');
    }

    public function show(Request $request, Investor $investor): View
    {
        abort_unless($request->user()->can('investor.view'), 403);
        $user = $request->user();
        $agreements = InvestorFlatAgreement::query()->visibleTo($user)->whereBelongsTo($investor)
            ->with(['flat:id,public_id,flat_no,building_id,project_id', 'flat.building:id,code', 'project:id,code,name'])->orderByRaw("FIELD(status, 'active', 'pending', 'draft', 'rejected', 'closed', 'cancelled')")->orderByDesc('id')->get();
        $totals = $this->ledgerScope($request, $investor)->selectRaw("
            COALESCE(SUM(CASE COALESCE(o.entry_kind, e.entry_kind) WHEN 'capital_in' THEN e.amount WHEN 'capital_out' THEN -e.amount ELSE 0 END),0) AS capital,
            COALESCE(SUM(CASE COALESCE(o.entry_kind, e.entry_kind) WHEN 'profit_payable' THEN e.amount WHEN 'profit_paid' THEN -e.amount ELSE 0 END),0) AS profit_due")->toBase()->first();

        return view('investors.show', [
            'investor' => $investor,
            'agreements' => $agreements,
            'capital' => Money::amount((string) $totals->capital),
            'profitDue' => Money::amount((string) $totals->profit_due),
            'committed' => Money::amount((string) $agreements->where('status', 'active')->sum('committed_capital')),
            'activity' => Activity::query()->where('subject_type', $investor->getMorphClass())->where('subject_id', $investor->id)->with('causer')->latest()->limit(50)->get(),
        ]);
    }

    public function ledger(Request $request, Investor $investor): JsonResponse
    {
        abort_unless($request->user()->can('investor.ledger.view'), 403);
        $page = max(1, (int) $request->input('page', 1));
        $rows = $this->ledgerQuery($request, $investor)->forPage($page, 50)->get();
        $total = (clone $this->ledgerScope($request, $investor))->count();

        return Ajax::table(view('investors._ledger_rows', ['rows' => $rows])->render(), new LengthAwarePaginator($rows, $total, 50, $page));
    }

    public function ledgerExport(Request $request, Investor $investor): Response
    {
        abort_unless($request->user()->can('investor.ledger.view') && $request->user()->can('investor.ledger.export'), 403);

        return Report::make('Investor Ledger')
            ->meta(['Investor' => $investor->name.' ('.$investor->investor_code.')', 'Mobile' => Phone::display($investor->mobile), 'Unit' => $this->flatFilter($request)?->flat_no])
            ->columns(['Date' => 'date', 'Entry' => 'text', 'Description' => 'text', 'Capital' => 'money', 'Profit' => 'money', 'Capital Balance' => 'money', 'Profit Due' => 'money'])
            ->rows(fn () => $this->ledgerQuery($request, $investor)->lazy()->map(fn ($r) => [$r->entry_date, InvestorLedgerEntry::KINDS[$r->entry_kind] ?? $r->entry_kind, $r->description,
                (float) $r->capital_effect ? $r->capital_effect : null, (float) $r->profit_effect ? $r->profit_effect : null, $r->capital_balance, $r->profit_balance]))
            ->respond($request);
    }

    private function flatFilter(Request $request): ?Flat
    {
        return Flat::query()->visibleTo($request->user())->where('public_id', (string) $request->input('flat'))->first();
    }

    /** Entries on units in projects the user can see. */
    private function ledgerScope(Request $request, Investor $investor): Builder
    {
        $flat = $this->flatFilter($request);

        return InvestorLedgerEntry::query()->from('investor_ledger_entries as e')->leftJoin('investor_ledger_entries as o', 'o.id', '=', 'e.reversal_of_id')
            ->where('e.investor_id', $investor->id)
            ->whereIn('e.flat_id', Flat::query()->visibleTo($request->user())->select('flats.id'))
            ->when($flat, fn ($q) => $q->where('e.flat_id', $flat->id));
    }

    private function ledgerQuery(Request $request, Investor $investor): Builder
    {
        $capital = "CASE COALESCE(o.entry_kind, e.entry_kind) WHEN 'capital_in' THEN e.amount WHEN 'capital_out' THEN -e.amount ELSE 0 END";
        $profit = "CASE COALESCE(o.entry_kind, e.entry_kind) WHEN 'profit_payable' THEN e.amount WHEN 'profit_paid' THEN -e.amount ELSE 0 END";

        return $this->ledgerScope($request, $investor)->select('e.id', 'e.entry_date', 'e.entry_kind', 'e.description')
            ->selectRaw("{$capital} AS capital_effect, {$profit} AS profit_effect")
            ->selectRaw("SUM({$capital}) OVER (ORDER BY e.entry_date, e.id) AS capital_balance, SUM({$profit}) OVER (ORDER BY e.entry_date, e.id) AS profit_balance")
            ->orderBy('e.entry_date')->orderBy('e.id');
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('investor.view'), 403);
        $visibleFlats = Flat::query()->visibleTo($request->user())->select('flats.id');
        $query = Investor::query()
            ->withCount(['agreements as active_agreements' => fn ($q) => $q->where('status', 'active')->whereIn('flat_id', $visibleFlats)])
            ->addSelect(['capital' => InvestorCapitalTransaction::query()->whereColumn('investor_id', 'investors.id')->where('status', 'posted')->whereIn('flat_id', $visibleFlats)
                ->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'contribution' THEN amount ELSE -amount END),0)")]);
        if (array_key_exists($status = (string) $request->input('status'), Investor::STATUSES)) {
            $query->where('status', $status);
        }

        return TableQuery::prepare($query, $request, ['name', 'investor_code', 'mobile', 'email'], ['name' => 'name', 'code' => 'investor_code'], 'name', 'asc');
    }

    private function input(Request $request): array
    {
        $request->merge(['mobile' => $request->filled('mobile') ? (Phone::normalize((string) $request->input('mobile')) ?: (string) $request->input('mobile')) : null]);

        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'mobile' => ['nullable', 'string', 'regex:/^\+?\d{10,15}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::in(array_keys(Investor::STATUSES))],
        ], ['mobile.regex' => 'Enter a valid mobile number.']);
    }

    private function form(Investor $investor, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('investors._form', ['investor' => $investor])->render()]);
    }
}
