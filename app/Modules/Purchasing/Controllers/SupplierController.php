<?php

namespace App\Modules\Purchasing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierLedgerEntry;
use App\Modules\Purchasing\Services\PurchasingService;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\Format;
use App\Support\ListSummary;
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

class SupplierController extends Controller
{
    public function __construct(private PurchasingService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('suppliers.view'), 403);

        return view('purchasing.suppliers.index');
    }

    public function table(Request $request): JsonResponse
    {
        $query = $this->listing($request);
        $suppliers = TableQuery::paginate($query, $request);

        return Ajax::table(view('purchasing.suppliers._rows', ['suppliers' => $suppliers])->render(), $suppliers, ['stats' => $this->summary($request)->render()]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('purchases.export'), 403);

        return Report::make('Suppliers')->meta(['Status' => Supplier::STATUSES[(string) $request->input('status')] ?? null])
            ->columns(['Code' => 'text', 'Name' => 'text', 'Contact' => 'text', 'Mobile' => 'text', 'TIN / BIN' => 'text', 'Payable' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (Supplier $s) => [$s->supplier_code, $s->name, $s->contact_person, $s->mobile ? Phone::display($s->mobile) : null, $s->tax_id,
                Money::sub((string) ($s->charges ?? '0'), (string) ($s->payments ?? '0')), Supplier::STATUSES[$s->status]]))
            ->respond($request);
    }

    private function summary(Request $request): ListSummary
    {
        $balance = '(SELECT COALESCE(SUM(l.charge_amount - l.payment_amount), 0) FROM supplier_ledger_entries l WHERE l.supplier_id = suppliers.id)';
        $paid = "(SELECT COALESCE(SUM(a.amount), 0) FROM supplier_payment_allocations a JOIN supplier_payments p ON p.id = a.payment_id WHERE a.supplier_bill_id = b.id AND p.status = 'approved')";
        $row = ListSummary::aggregate($this->listing($request), [
            'payable' => "SUM({$balance})",
            'owing' => "SUM(CASE WHEN {$balance} > 0 THEN 1 ELSE 0 END)",
            'overdue' => ["SUM((SELECT COALESCE(SUM(b.total_amount - {$paid}), 0) FROM supplier_bills b WHERE b.supplier_id = suppliers.id AND b.status = 'approved' AND b.due_date < ?))", [now(config('app.display_timezone'))->toDateString()]],
        ]);

        return ListSummary::make($request)
            ->money('Payable', $row->payable, 'briefcase', 'warning', note: ListSummary::many($row->owing, 'supplier'))
            ->money('Past Due Date', $row->overdue, 'alert', 'danger');
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('suppliers.view'), 403);

        $query = Supplier::query()
            ->withSum('ledgerEntries as charges', 'charge_amount')
            ->withSum('ledgerEntries as payments', 'payment_amount');
        if (array_key_exists($status = (string) $request->input('status'), Supplier::STATUSES)) {
            $query->where('status', $status);
        }

        return TableQuery::prepare($query, $request, ['name', 'supplier_code', 'contact_person', 'mobile'], ['name' => 'name', 'code' => 'supplier_code'], 'name');
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('suppliers.create'), 403);

        return $this->form(new Supplier(['status' => 'active']), 'New Supplier');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('suppliers.create'), 403);
        $data = $this->validated($request, null);
        $supplier = $this->service->createSupplier(collect($data)->except('opening_balance')->all(), $data['opening_balance'] ?? '0', $request->user());

        return Ajax::success('Supplier created.', ['redirect' => route('suppliers.show', $supplier)]);
    }

    public function edit(Request $request, Supplier $supplier): JsonResponse
    {
        abort_unless($request->user()->can('suppliers.update'), 403);

        return $this->form($supplier, 'Edit Supplier');
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        abort_unless($request->user()->can('suppliers.update'), 403);
        $supplier->update(collect($this->validated($request, $supplier))->except('opening_balance')->all());

        return Ajax::success('Supplier updated.');
    }

    public function show(Request $request, Supplier $supplier): View
    {
        abort_unless($request->user()->can('suppliers.view'), 403);

        $bills = SupplierBill::query()->whereBelongsTo($supplier)->whereIn('status', ['approved', 'paid'])->with('allocations.payment')->get();
        $aging = ['current' => '0', '31-60' => '0', '61-90' => '0', '90+' => '0'];
        foreach ($bills as $bill) {
            $due = $bill->outstanding();
            if (! Money::isPositive($due)) {
                continue;
            }
            $days = (int) ($bill->due_date ?? $bill->invoice_date)->diffInDays(now(), false);
            $bucket = match (true) {
                $days <= 30 => 'current', $days <= 60 => '31-60', $days <= 90 => '61-90', default => '90+'
            };
            $aging[$bucket] = Money::add($aging[$bucket], $due);
        }

        return view('purchasing.suppliers.show', [
            'supplier' => $supplier,
            'balance' => $supplier->balance(),
            'billed' => Money::amount((string) $supplier->ledgerEntries()->where('entry_type', 'bill')->sum('charge_amount')),
            'paid' => Money::amount((string) $supplier->ledgerEntries()->where('entry_type', 'payment')->sum('payment_amount')),
            'openOrders' => PurchaseOrder::query()->whereBelongsTo($supplier)->whereIn('status', ['submitted', 'approved', 'partially_received'])->count(),
            'aging' => $aging,
        ]);
    }

    public function ledger(Request $request, Supplier $supplier): JsonResponse
    {
        abort_unless($request->user()->can('suppliers.ledger.view'), 403);

        $page = max(1, (int) $request->input('page', 1));
        $rows = $this->statement($supplier)->forPage($page, 50)->get();
        $total = $supplier->ledgerEntries()->count();

        return Ajax::table(view('purchasing.suppliers._ledger', ['rows' => $rows])->render(),
            new LengthAwarePaginator($rows, $total, 50, $page));
    }

    public function exportLedger(Request $request, Supplier $supplier): Response
    {
        abort_unless($request->user()->can('suppliers.ledger.export'), 403);

        return Report::make('Supplier Statement')
            ->meta(['Supplier' => $supplier->name.' ('.$supplier->supplier_code.')', 'Payable' => Format::money($supplier->balance())])
            ->columns(['Date' => 'date', 'Type' => 'text', 'Description' => 'text', 'Charge' => 'money', 'Payment' => 'money', 'Balance' => 'money'])
            ->rows(fn () => $this->statement($supplier)->lazy()->map(fn ($r) => [$r->entry_date, SupplierLedgerEntry::TYPES[$r->entry_type] ?? $r->entry_type, $r->description,
                (float) $r->charge_amount ? $r->charge_amount : null, (float) $r->payment_amount ? $r->payment_amount : null, $r->running_balance]))
            ->respond($request);
    }

    public function activity(Request $request, Supplier $supplier): JsonResponse
    {
        abort_unless($request->user()->can('suppliers.view'), 403);
        $entries = Activity::query()->where('subject_type', $supplier->getMorphClass())->where('subject_id', $supplier->id)->with('causer')->latest()->limit(100)->get();

        return Ajax::success(null, ['html' => view('projects.partials._activity', ['entries' => $entries])->render()]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['suppliers.view', 'purchases.view']), 403);

        [$results, $more] = TableQuery::options(Supplier::query()->where('status', 'active')->orderBy('name'), $request, ['name', 'supplier_code', 'mobile'],
            fn (Supplier $s) => ['id' => $s->public_id, 'text' => $s->name.' · '.$s->supplier_code]);

        return Ajax::options($results, $more);
    }

    /** Ledger rows in posting order with a running balance (MySQL 8 window function). */
    private function statement(Supplier $supplier)
    {
        return SupplierLedgerEntry::query()->where('supplier_id', $supplier->id)
            ->select('*')
            ->selectRaw('SUM(charge_amount - payment_amount) OVER (ORDER BY entry_date, id) AS running_balance')
            ->orderBy('entry_date')->orderBy('id');
    }

    private function validated(Request $request, ?Supplier $supplier): array
    {
        $request->merge([
            'mobile' => Phone::normalize($request->input('mobile')),
            'email' => $request->filled('email') ? mb_strtolower(trim($request->input('email'))) : null,
            'opening_balance' => $request->filled('opening_balance') ? str_replace(',', '', (string) $request->input('opening_balance')) : '0',
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'mobile' => ['nullable', 'string', 'regex:/^\+?\d{10,15}$/'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::in(array_keys(Supplier::STATUSES))],
            'opening_balance' => [$supplier ? 'nullable' : 'required', 'numeric', 'between:-9999999999999,9999999999999', 'decimal:0,2'],
        ], ['mobile.regex' => 'Enter a valid mobile number.']);
    }

    private function form(Supplier $supplier, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('purchasing.suppliers._form', ['supplier' => $supplier])->render()]);
    }
}
