<?php

namespace App\Modules\Investors\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Investor;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorFlatAgreement;
use App\Models\Project;
use App\Modules\Investors\Services\InvestorService;
use App\Modules\Sales\Controllers\Concerns\AuthorizesProjectRecords;
use App\Support\Ajax;
use App\Support\Export\Document;
use App\Support\Export\Report;
use App\Support\Phone;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

class CapitalController extends Controller
{
    use AuthorizesProjectRecords;

    public const METHODS = ['cash' => 'Cash', 'bank' => 'Bank Transfer', 'cheque' => 'Cheque', 'mobile' => 'Mobile Banking'];

    public function __construct(private InvestorService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('investor.view'), 403);

        return view('investors.capital.index');
    }

    public function table(Request $request): JsonResponse
    {
        $rows = TableQuery::paginate($this->listing($request), $request);

        return Ajax::table(view('investors.capital._rows', ['transactions' => $rows, 'showInvestor' => ! $request->filled('investor')])->render(), $rows);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('investor.ledger.export'), 403);

        return Report::make('Investor Capital')
            ->meta(['Type' => InvestorCapitalTransaction::TYPES[(string) $request->input('type')] ?? null, 'Status' => InvestorCapitalTransaction::STATUSES[(string) $request->input('status')] ?? null,
                'Investor' => Investor::findByPublicId($request->input('investor'))?->name, 'Project' => Project::findByPublicId($request->input('project'))?->name])
            ->columns(['Transaction No' => 'text', 'Date' => 'date', 'Investor' => 'text', 'Project' => 'text', 'Unit' => 'text', 'Type' => 'text', 'Method' => 'text', 'Reference' => 'text', 'Amount' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (InvestorCapitalTransaction $t) => [$t->transaction_no, $t->transaction_date, $t->investor->name, $t->project->code, $t->flat->flat_no,
                InvestorCapitalTransaction::TYPES[$t->transaction_type], self::METHODS[$t->payment_method] ?? null, $t->reference_no, $t->amount, InvestorCapitalTransaction::STATUSES[$t->status]]))
            ->respond($request);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['investor.capital.record', 'investor.capital.return']), 403);
        $agreement = InvestorFlatAgreement::query()->visibleTo($request->user())->where('public_id', (string) $request->input('agreement'))->whereIn('status', ['active', 'closed'])
            ->with(['investor', 'flat', 'project'])->first();
        $type = $request->input('type') === 'capital_return' ? 'capital_return' : 'contribution';

        return Ajax::success(null, ['title' => $type === 'capital_return' ? 'Return Capital' : 'Receive Capital', 'html' => view('investors.capital._form', [
            'agreement' => $agreement, 'type' => $type, 'key' => (string) Str::uuid(),
            'balance' => $agreement ? InvestorService::capitalBalance($agreement->investor_id, $agreement->flat_id) : null,
        ])->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['investor.capital.record', 'investor.capital.return']), 403);
        $request->merge(['amount' => str_replace(',', '', (string) $request->input('amount'))]);
        $data = $request->validate([
            'agreement' => ['required', 'string', 'size:16'],
            'transaction_type' => ['required', Rule::in(array_keys(InvestorCapitalTransaction::TYPES))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
            'transaction_date' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'payment_method' => ['required', Rule::in(array_keys(self::METHODS))],
            'reference_no' => [Rule::requiredIf(in_array($request->input('payment_method'), ['bank', 'cheque', 'mobile'], true)), 'nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ], ['reference_no.required' => 'Enter the transaction or cheque reference.']);
        abort_unless($request->user()->can($data['transaction_type'] === 'contribution' ? 'investor.capital.record' : 'investor.capital.return'), 403);
        $agreement = InvestorFlatAgreement::query()->visibleTo($request->user())->where('public_id', $data['agreement'])->first()
            ?? throw ValidationException::withMessages(['agreement' => 'Select an agreement you can access.']);
        $data['amount'] = (string) $data['amount'];

        $tx = $this->service->recordCapital($agreement, $data['transaction_type'], $data, $request->user(), $data['idempotency_key']);

        return Ajax::success($tx->transaction_no.' recorded.', ['redirect' => route('investor-capital.show', $tx)]);
    }

    public function show(Request $request, InvestorCapitalTransaction $transaction): View
    {
        $this->authorizeRecord($request, 'investor.view', $transaction);
        $transaction->load(['investor', 'agreement', 'flat.building', 'project', 'creator', 'approver']);

        return view('investors.capital.show', [
            'tx' => $transaction,
            'activity' => Activity::query()->where('subject_type', $transaction->getMorphClass())->where('subject_id', $transaction->id)->with('causer')->latest()->limit(50)->get(),
        ]);
    }

    public function post(Request $request, InvestorCapitalTransaction $transaction): JsonResponse
    {
        $this->authorizeRecord($request, 'investor.capital.approve', $transaction);
        try {
            $this->service->postCapital($transaction, $request->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['transaction' => collect($e->errors())->flatten()->first()]);
        }

        return Ajax::success($transaction->transaction_no.' posted.');
    }

    public function reasonForm(Request $request, InvestorCapitalTransaction $transaction, string $action): JsonResponse
    {
        in_array($action, ['reject', 'reverse'], true) || abort(404);
        $this->authorizeRecord($request, 'investor.capital.approve', $transaction);

        return Ajax::success(null, ['title' => Str::headline($action).' '.$transaction->transaction_no, 'html' => view('partials.reason-form', [
            'action' => route('investor-capital.reason.store', [$transaction, $action]), 'button' => Str::headline($action), 'reload' => true,
        ])->render()]);
    }

    public function reason(Request $request, InvestorCapitalTransaction $transaction, string $action): JsonResponse
    {
        in_array($action, ['reject', 'reverse'], true) || abort(404);
        $this->authorizeRecord($request, 'investor.capital.approve', $transaction);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];
        $action === 'reject' ? $this->service->rejectCapital($transaction, $reason, $request->user()) : $this->service->reverseCapital($transaction, $reason, $request->user());

        return Ajax::success($action === 'reject' ? 'Transaction rejected.' : 'Transaction reversed.');
    }

    public function document(Request $request, InvestorCapitalTransaction $transaction): Response
    {
        $this->authorizeRecord($request, 'investor.view', $transaction);
        $transaction->load(['investor', 'agreement', 'flat.building', 'project', 'creator', 'approver']);
        $i = $transaction->investor;
        $in = $transaction->transaction_type === 'contribution';

        return Document::make($in ? 'Capital Receipt' : 'Capital Return Voucher', $transaction->transaction_no)
            ->stamp($transaction->status === 'posted' ? null : InvestorCapitalTransaction::STATUSES[$transaction->status])
            ->parties([($in ? 'Received From' : 'Paid To') => [$i->name, $i->investor_code, Phone::display($i->mobile)], 'Investment' => [$transaction->project->name, 'Unit '.$transaction->flat->flat_no, $transaction->agreement->agreement_no]])
            ->meta(['Date' => $transaction->transaction_date, 'Method' => self::METHODS[$transaction->payment_method] ?? null, 'Reference' => $transaction->reference_no, 'Prepared By' => $transaction->creator->name, 'Posted By' => $transaction->approver?->name])
            ->summary([($in ? 'Amount Received' : 'Amount Paid') => $transaction->amount])
            ->notes($transaction->notes)
            ->signatures([$in ? 'Investor' : 'Received by', 'Prepared by', 'Authorised signatory'])
            ->respond($request);
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('investor.view'), 403);
        $query = InvestorCapitalTransaction::query()->visibleTo($request->user())
            ->with(['investor:id,public_id,name,investor_code', 'flat:id,public_id,flat_no', 'project:id,code', 'agreement:id,public_id,agreement_no']);
        foreach (['status' => InvestorCapitalTransaction::STATUSES, 'type' => InvestorCapitalTransaction::TYPES] as $key => $allowed) {
            if (array_key_exists($value = (string) $request->input($key), $allowed)) {
                $query->where($key === 'type' ? 'transaction_type' : 'status', $value);
            }
        }
        if ($investor = Investor::findByPublicId($request->input('investor'))) {
            $query->where('investor_id', $investor->id);
        }
        if ($agreement = InvestorFlatAgreement::findByPublicId($request->input('agreement'))) {
            $query->where('investor_id', $agreement->investor_id)->where('flat_id', $agreement->flat_id);
        }
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }

        return TableQuery::prepare($query, $request, ['transaction_no', 'reference_no', 'investor.name'], ['date' => 'transaction_date', 'amount' => 'amount'], 'date', 'desc');
    }
}
