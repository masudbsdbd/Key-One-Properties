<?php

namespace App\Modules\Investors\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Flat;
use App\Models\Investor;
use App\Models\InvestorFlatAgreement;
use App\Models\Project;
use App\Modules\Investors\Services\InvestorService;
use App\Modules\Sales\Controllers\Concerns\AuthorizesProjectRecords;
use App\Support\Ajax;
use App\Support\Export\Documents;
use App\Support\Export\Report;
use App\Support\Money;
use App\Support\TableQuery;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

class AgreementController extends Controller
{
    use AuthorizesProjectRecords;

    public function __construct(private InvestorService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('investor.view'), 403);

        return view('investors.agreements.index');
    }

    public function table(Request $request): JsonResponse
    {
        $agreements = TableQuery::paginate($this->listing($request), $request);

        return Ajax::table(view('investors.agreements._rows', ['agreements' => $agreements, 'showInvestor' => ! $request->filled('investor')])->render(), $agreements);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('investor.ledger.export'), 403);

        return Report::make('Investment Agreements')
            ->meta(['Status' => InvestorFlatAgreement::STATUSES[(string) $request->input('status')] ?? null, 'Project' => Project::findByPublicId($request->input('project'))?->name, 'Investor' => Investor::findByPublicId($request->input('investor'))?->name])
            ->columns(['Agreement No' => 'text', 'Investor' => 'text', 'Project' => 'text', 'Unit' => 'text', 'Share %' => 'text', 'Committed Capital' => 'money', 'From' => 'date', 'To' => 'date', 'Version' => 'number', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (InvestorFlatAgreement $a) => [$a->agreement_no, $a->investor->name, $a->project->code, $a->flat->flat_no,
                self::pct($a->profit_share_percent), $a->committed_capital, $a->effective_from, $a->effective_to, $a->version, InvestorFlatAgreement::STATUSES[$a->status]]))
            ->respond($request);
    }

    public static function pct(string|float|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.').'%';
    }

    /** Units of projects the user can see. */
    public function flatOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['investor.agreement.create', 'investor.agreement.edit', 'investor.view']), 403);
        $query = Flat::query()->visibleTo($request->user())->with(['project:id,code', 'building:id,code'])->orderBy('project_id')->orderBy('flat_no');
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }
        [$results, $more] = TableQuery::options($query, $request, ['flat_no'],
            fn (Flat $f) => ['id' => $f->public_id, 'text' => $f->project->code.' · '.$f->building->code.' · Unit '.$f->flat_no]);

        return Ajax::options($results, $more);
    }

    /** Agreements that can take capital (active for contributions, also closed for returns). */
    public function agreementOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['investor.capital.record', 'investor.capital.return']), 403);
        $query = InvestorFlatAgreement::query()->visibleTo($request->user())->whereIn('status', ['active', 'closed'])
            ->with(['investor:id,name', 'flat:id,flat_no', 'project:id,code'])->orderByDesc('id');
        if ($investor = Investor::findByPublicId($request->input('investor'))) {
            $query->where('investor_id', $investor->id);
        }
        [$results, $more] = TableQuery::options($query, $request, ['agreement_no', 'investor.name', 'flat.flat_no'],
            fn (InvestorFlatAgreement $a) => ['id' => $a->public_id, 'text' => $a->agreement_no.' · '.$a->investor->name.' · '.$a->project->code.' Unit '.$a->flat->flat_no.($a->status === 'closed' ? ' (closed)' : '')]);

        return Ajax::options($results, $more);
    }

    /** Share gauge for the agreement form. */
    public function shares(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['investor.agreement.create', 'investor.agreement.edit']), 403);
        $flat = Flat::query()->visibleTo($request->user())->where('public_id', (string) $request->input('flat'))->with(['project', 'building'])->first();
        if (! $flat) {
            return Ajax::success(null, ['html' => '']);
        }
        $from = $this->date($request->input('from')) ?? now(config('app.display_timezone'))->startOfDay();
        $to = $this->date($request->input('to'));
        $editing = InvestorFlatAgreement::query()->visibleTo($request->user())->where('public_id', (string) $request->input('agreement'))->first();
        $summary = InvestorService::shareSummary($flat, $from, $to, array_filter([$editing?->id, $editing?->supersedes_id]));

        return Ajax::success(null, ['html' => view('investors.agreements._gauge', ['flat' => $flat, 'summary' => $summary, 'from' => $from, 'to' => $to])->render()]);
    }

    private function date(mixed $value): ?Carbon
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? Carbon::parse($value) : null;
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('investor.agreement.create'), 403);
        $agreement = new InvestorFlatAgreement(['effective_from' => now(config('app.display_timezone'))]);
        $agreement->setRelation('investor', Investor::query()->where('public_id', (string) $request->input('investor'))->where('status', 'active')->first());
        $agreement->setRelation('flat', Flat::query()->visibleTo($request->user())->where('public_id', (string) $request->input('flat'))->with(['project', 'building'])->first());

        return $this->form($agreement, 'New Investment Agreement');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('investor.agreement.create'), 403);
        [$investor, $flat, $data] = $this->input($request);
        $agreement = $this->service->saveAgreement(null, $investor, $flat, $data, $request->user());
        if ($request->boolean('submit')) {
            abort_unless($request->user()->can('investor.agreement.edit'), 403);
            $this->service->submitAgreement($agreement, $request->user());
        }

        return Ajax::success('Agreement '.$agreement->agreement_no.' saved.', ['redirect' => route('investor-agreements.show', $agreement)]);
    }

    public function show(Request $request, InvestorFlatAgreement $agreement): View
    {
        $this->authorizeRecord($request, 'investor.view', $agreement);
        $agreement->load(['investor', 'flat.building', 'flat.floor', 'project', 'creator', 'approver', 'closer', 'supersedes']);
        $versions = $agreement->versions()->with('approver:id,name')->get();
        $capital = $agreement->capitalTransactions()->latest('transaction_date')->latest('id')->get();

        return view('investors.agreements.show', [
            'agreement' => $agreement,
            'versions' => $versions,
            'capital' => $capital,
            'balance' => InvestorService::capitalBalance($agreement->investor_id, $agreement->flat_id),
            'summary' => InvestorService::shareSummary($agreement->flat, now(config('app.display_timezone'))->startOfDay(), null),
            'openAmendment' => $versions->first(fn ($v) => $v->supersedes_id === $agreement->id && in_array($v->status, ['draft', 'pending', 'rejected'], true)),
            'activity' => Activity::query()->where('subject_type', $agreement->getMorphClass())->where('subject_id', $agreement->id)->with('causer')->latest()->limit(50)->get(),
        ]);
    }

    public function edit(Request $request, InvestorFlatAgreement $agreement): JsonResponse
    {
        $this->authorizeRecord($request, 'investor.agreement.edit', $agreement);

        return $this->form($agreement->load(['investor', 'flat.project', 'flat.building']), 'Edit '.$agreement->agreement_no);
    }

    public function update(Request $request, InvestorFlatAgreement $agreement): JsonResponse
    {
        $this->authorizeRecord($request, 'investor.agreement.edit', $agreement);
        [$investor, $flat, $data] = $this->input($request);
        $this->service->saveAgreement($agreement, $investor, $flat, $data, $request->user());

        return Ajax::success('Agreement updated.');
    }

    public function decide(Request $request, InvestorFlatAgreement $agreement, string $action): JsonResponse
    {
        $permission = ['submit' => 'investor.agreement.edit', 'approve' => 'investor.agreement.approve', 'amend' => 'investor.agreement.create'][$action] ?? abort(404);
        $this->authorizeRecord($request, $permission, $agreement);
        if ($action === 'amend') {
            $draft = $this->service->amend($agreement, $request->user());

            return Ajax::success('Amendment '.$draft->agreement_no.' created.', ['redirect' => route('investor-agreements.show', $draft)]);
        }
        try {
            $action === 'submit' ? $this->service->submitAgreement($agreement, $request->user()) : $this->service->approveAgreement($agreement, $request->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['agreement' => collect($e->errors())->flatten()->first()]);
        }

        return Ajax::success($action === 'submit' ? 'Agreement sent for approval.' : 'Agreement approved.');
    }

    public function reasonForm(Request $request, InvestorFlatAgreement $agreement, string $action): JsonResponse
    {
        $permission = ['reject' => 'investor.agreement.approve', 'cancel' => 'investor.agreement.edit', 'close' => 'investor.agreement.close'][$action] ?? abort(404);
        $this->authorizeRecord($request, $permission, $agreement);
        $view = $action === 'close' ? view('investors.agreements._close', ['agreement' => $agreement]) : view('partials.reason-form', [
            'action' => route('investor-agreements.reason.store', [$agreement, $action]), 'button' => Str::headline($action), 'reload' => true,
        ]);

        return Ajax::success(null, ['title' => Str::headline($action).' '.$agreement->agreement_no, 'html' => $view->render()]);
    }

    public function reason(Request $request, InvestorFlatAgreement $agreement, string $action): JsonResponse
    {
        $permission = ['reject' => 'investor.agreement.approve', 'cancel' => 'investor.agreement.edit', 'close' => 'investor.agreement.close'][$action] ?? abort(404);
        $this->authorizeRecord($request, $permission, $agreement);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'effective_to' => [Rule::requiredIf($action === 'close'), 'nullable', 'date'],
        ]);
        match ($action) {
            'reject' => $this->service->rejectAgreement($agreement, $data['reason'], $request->user()),
            'cancel' => $this->service->cancelAgreement($agreement, $data['reason'], $request->user()),
            'close' => $this->service->closeAgreement($agreement, $data['effective_to'], $data['reason'], $request->user()),
        };

        return Ajax::success(['reject' => 'Agreement rejected.', 'cancel' => 'Agreement cancelled.', 'close' => 'Agreement closed.'][$action]);
    }

    public function document(Request $request, InvestorFlatAgreement $agreement): Response
    {
        $this->authorizeRecord($request, 'investor.view', $agreement);

        return Documents::investorAgreement($agreement)
            ->respond($request);
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('investor.view'), 403);
        $query = InvestorFlatAgreement::query()->visibleTo($request->user())
            ->with(['investor:id,public_id,name,investor_code', 'flat:id,public_id,flat_no,building_id', 'flat.building:id,code', 'project:id,code']);
        if (array_key_exists($status = (string) $request->input('status'), InvestorFlatAgreement::STATUSES)) {
            $query->where('status', $status);
        }
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }
        if ($investor = Investor::findByPublicId($request->input('investor'))) {
            $query->where('investor_id', $investor->id);
        }
        if ($flat = Flat::findByPublicId($request->input('flat'))) {
            $query->where('flat_id', $flat->id);
        }

        return TableQuery::prepare($query, $request, ['agreement_no', 'investor.name', 'flat.flat_no'], ['from' => 'effective_from', 'share' => 'profit_share_percent', 'capital' => 'committed_capital'], 'from', 'desc');
    }

    /** @return array{0: Investor, 1: Flat, 2: array} */
    private function input(Request $request): array
    {
        foreach (['committed_capital', 'profit_share_percent'] as $f) {
            $request->merge([$f => str_replace([',', '%'], '', (string) $request->input($f))]);
        }
        $data = $request->validate([
            'investor' => ['required', 'string', 'size:16'],
            'flat' => ['required', 'string', 'size:16'],
            'committed_capital' => ['required', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
            'profit_share_percent' => ['required', 'numeric', 'gt:0', 'max:100', 'decimal:0,4'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ]);
        $investor = Investor::query()->where('public_id', $data['investor'])->first() ?? throw ValidationException::withMessages(['investor' => 'Select an investor.']);
        $flat = Flat::query()->visibleTo($request->user())->where('public_id', $data['flat'])->first()
            ?? throw ValidationException::withMessages(['flat' => 'Select a unit you can access.']);
        $data['committed_capital'] = Money::amount((string) $data['committed_capital']);
        $data['profit_share_percent'] = Money::rate((string) $data['profit_share_percent']);

        return [$investor, $flat, $data];
    }

    private function form(InvestorFlatAgreement $agreement, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('investors.agreements._form', ['agreement' => $agreement])->render()]);
    }
}
