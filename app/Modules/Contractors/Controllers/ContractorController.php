<?php

namespace App\Modules\Contractors\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ContractorLedgerEntry;
use App\Models\ContractorWorkOrder;
use App\Modules\Contractors\Services\ContractorService;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\Format;
use App\Support\Money;
use App\Support\Phone;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

class ContractorController extends Controller
{
    public function __construct(private ContractorService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('contractors.view'), 403);

        return view('contractors.directory.index');
    }

    public function table(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('contractors.view'), 403);

        $query = Contractor::query()
            ->withSum('ledgerEntries as charges', 'charge_amount')
            ->withSum('ledgerEntries as payments', 'payment_amount')
            ->withCount(['workOrders as active_orders' => fn ($q) => $q->where('status', 'approved')]);
        if (array_key_exists($status = (string) $request->input('status'), Contractor::STATUSES)) {
            $query->where('status', $status);
        }

        $contractors = TableQuery::apply($query, $request, ['name', 'contractor_code', 'trade', 'contact_person', 'mobile'], ['name' => 'name', 'code' => 'contractor_code'], 'name');

        return Ajax::table(view('contractors.directory._rows', ['contractors' => $contractors])->render(), $contractors);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('contractors.create'), 403);

        return $this->form(new Contractor(['status' => 'active']), 'New Contractor');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('contractors.create'), 403);
        $contractor = $this->service->createContractor($this->validated($request), $request->user());

        return Ajax::success('Contractor created.', ['redirect' => route('contractors.show', $contractor)]);
    }

    public function edit(Request $request, Contractor $contractor): JsonResponse
    {
        abort_unless($request->user()->can('contractors.update'), 403);

        return $this->form($contractor, 'Edit Contractor');
    }

    public function update(Request $request, Contractor $contractor): JsonResponse
    {
        abort_unless($request->user()->can('contractors.update'), 403);
        $contractor->update($this->validated($request));

        return Ajax::success('Contractor updated.');
    }

    public function show(Request $request, Contractor $contractor): View
    {
        abort_unless($request->user()->can('contractors.view'), 403);
        $entries = $contractor->ledgerEntries();

        return view('contractors.directory.show', [
            'contractor' => $contractor,
            'balance' => $contractor->balance(),
            'billed' => Money::amount((string) (clone $entries)->whereIn('entry_type', ['bill', 'retention_release'])->sum('charge_amount')),
            'paid' => Money::amount((string) (clone $entries)->whereIn('entry_type', ['payment', 'advance'])->sum('payment_amount')),
            'activeOrders' => ContractorWorkOrder::query()->whereBelongsTo($contractor)->visibleTo($request->user())->where('status', 'approved')->count(),
        ]);
    }

    public function ledger(Request $request, Contractor $contractor): JsonResponse
    {
        abort_unless($request->user()->can('contractors.ledger.view'), 403);
        $page = max(1, (int) $request->input('page', 1));
        $rows = $this->statement($contractor)->forPage($page, 50)->get();

        return Ajax::table(view('contractors.directory._ledger', ['rows' => $rows])->render(),
            new LengthAwarePaginator($rows, $contractor->ledgerEntries()->count(), 50, $page));
    }

    public function exportLedger(Request $request, Contractor $contractor): Response
    {
        abort_unless($request->user()->can('contractors.ledger.view'), 403);

        return Report::make('Contractor Statement')
            ->meta(['Contractor' => $contractor->name.' ('.$contractor->contractor_code.')', 'Payable' => Format::money($contractor->balance())])
            ->columns(['Date' => 'date', 'Type' => 'text', 'Description' => 'text', 'Charge' => 'money', 'Payment' => 'money', 'Balance' => 'money'])
            ->rows(fn () => $this->statement($contractor)->lazy()->map(fn ($r) => [$r->entry_date, ContractorLedgerEntry::TYPES[$r->entry_type] ?? $r->entry_type, $r->description,
                (float) $r->charge_amount ? $r->charge_amount : null, (float) $r->payment_amount ? $r->payment_amount : null, $r->running_balance]))
            ->respond($request);
    }

    public function activity(Request $request, Contractor $contractor): JsonResponse
    {
        abort_unless($request->user()->can('contractors.view'), 403);
        $entries = Activity::query()->where('subject_type', $contractor->getMorphClass())->where('subject_id', $contractor->id)->with('causer')->latest()->limit(100)->get();

        return Ajax::success(null, ['html' => view('projects.partials._activity', ['entries' => $entries])->render()]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('contractors.view'), 403);

        [$results, $more] = TableQuery::options(Contractor::query()->where('status', 'active')->orderBy('name'), $request, ['name', 'contractor_code', 'trade', 'mobile'],
            fn (Contractor $c) => ['id' => $c->public_id, 'text' => $c->name.' · '.$c->contractor_code]);

        return Ajax::options($results, $more);
    }

    private function statement(Contractor $contractor)
    {
        return ContractorLedgerEntry::query()->where('contractor_id', $contractor->id)->select('*')
            ->selectRaw('SUM(charge_amount - payment_amount) OVER (ORDER BY entry_date, id) AS running_balance')
            ->orderBy('entry_date')->orderBy('id');
    }

    private function validated(Request $request): array
    {
        $request->merge([
            'mobile' => Phone::normalize($request->input('mobile')),
            'email' => $request->filled('email') ? mb_strtolower(trim($request->input('email'))) : null,
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'trade' => ['nullable', 'string', 'max:80'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'mobile' => ['nullable', 'string', 'regex:/^\+?\d{10,15}$/'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::in(array_keys(Contractor::STATUSES))],
        ], ['mobile.regex' => 'Enter a valid mobile number.']);
    }

    private function form(Contractor $contractor, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('contractors.directory._form', ['contractor' => $contractor])->render()]);
    }
}
