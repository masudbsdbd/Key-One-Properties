<?php

namespace App\Modules\Collections\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\InstallmentReschedule;
use App\Models\InstallmentScheduleItem;
use App\Models\Project;
use App\Modules\Collections\Services\InstallmentService;
use App\Modules\Collections\Services\LateFeeService;
use App\Modules\Collections\Services\PaymentService;
use App\Modules\Sales\Controllers\Concerns\AuthorizesProjectRecords;
use App\Support\Ajax;
use App\Support\Export\Document;
use App\Support\Export\Report;
use App\Support\Format;
use App\Support\Money;
use App\Support\Phone;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InstallmentPlanController extends Controller
{
    use AuthorizesProjectRecords;

    public function __construct(private InstallmentService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('collections.plan.view'), 403);

        return view('collections.plans.index');
    }

    public function table(Request $request): JsonResponse
    {
        $plans = TableQuery::paginate($this->listing($request), $request);

        return Ajax::table(view('collections.plans._rows', ['plans' => $plans])->render(), $plans);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('collections.payment.statement.export'), 403);

        return Report::make('Installment Plans')->meta(['Status' => InstallmentPlan::STATUSES[(string) $request->input('status')] ?? null])
            ->columns(['Plan No' => 'text', 'Sale' => 'text', 'Customer' => 'text', 'Contract' => 'money', 'Booking Credit' => 'money', 'Principal' => 'money', 'Items' => 'number', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (InstallmentPlan $p) => [$p->plan_no, $p->sale->sale_no, $p->customer->full_name, $p->contract_value, $p->booking_credit, $p->principal_amount, $p->items_count, InstallmentPlan::STATUSES[$p->status]]))
            ->respond($request);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('collections.plan.create'), 403);
        $sale = FlatSale::query()->visibleTo($request->user())->where('public_id', (string) $request->input('sale'))->with(['customer', 'flat'])->first();

        return Ajax::success(null, ['title' => 'New Installment Plan', 'html' => view('collections.plans._form', [
            'sale' => $sale, 'credit' => $sale ? InstallmentService::bookingCredit($sale) : '0.00',
        ])->render()]);
    }

    /** Approved sales that have no live plan. */
    public function saleOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('collections.plan.create'), 403);
        $query = FlatSale::query()->visibleTo($request->user())->whereIn('status', array_diff(FlatSale::ACTIVE, ['cancel_requested']))
            ->whereDoesntHave('plans', fn ($q) => $q->whereIn('status', ['draft', 'pending', 'active']))->with(['customer:id,full_name', 'flat:id,flat_no'])->latest('id');

        [$results, $more] = TableQuery::options($query, $request, ['sale_no'], fn (FlatSale $s) => ['id' => $s->public_id, 'text' => $s->sale_no.' · '.$s->customer->full_name.' · Unit '.$s->flat->flat_no]);

        return Ajax::options($results, $more);
    }

    public function preview(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('collections.plan.create'), 403);
        [$sale, $data] = $this->input($request);
        $built = $this->service->build($sale, $data);

        return Ajax::success(null, ['html' => view('collections.plans._preview', ['built' => $built])->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('collections.plan.create'), 403);
        [$sale, $data] = $this->input($request);
        $plan = $this->service->create($sale, $data, $request->user());
        if ($request->boolean('submit')) {
            $this->service->submit($plan, $request->user());
        }

        return Ajax::success('Plan '.$plan->plan_no.' saved.', ['redirect' => route('installment-plans.show', $plan)]);
    }

    public function show(Request $request, InstallmentPlan $plan): View
    {
        $this->authorizeRecord($request, 'collections.plan.view', $plan);
        $plan->load(['sale.flat', 'sale.project', 'customer', 'creator', 'approver', 'items.assessments', 'reschedules.requester']);

        return view('collections.plans.show', [
            'plan' => $plan,
            'advance' => PaymentService::unappliedAdvance($plan->sale),
            'policy' => LateFeeService::policyFor($plan, now(config('app.display_timezone'))),
        ]);
    }

    public function decide(Request $request, InstallmentPlan $plan, string $action): JsonResponse
    {
        $this->authorizeRecord($request, $action === 'submit' || $action === 'discard' ? 'collections.plan.create' : 'collections.plan.approve', $plan);
        match ($action) {
            'submit' => $this->service->submit($plan, $request->user()),
            'approve' => $this->service->approve($plan, $request->user()),
            'reject' => $this->service->reject($plan, $request->user()),
            'discard' => $this->service->discard($plan, $request->user()),
        };

        return Ajax::success(['submit' => 'Plan sent for approval.', 'approve' => 'Plan is active.', 'reject' => 'Plan rejected.', 'discard' => 'Plan discarded.'][$action]);
    }

    public function assessFees(Request $request, InstallmentPlan $plan, LateFeeService $fees): JsonResponse
    {
        $this->authorizeRecord($request, 'collections.latefee.manage', $plan);
        $count = $fees->assess($plan, now(config('app.display_timezone')), $request->user());

        return Ajax::success($count ? $count.' late fee(s) charged.' : 'No late fees due.');
    }

    public function rescheduleForm(Request $request, InstallmentPlan $plan): JsonResponse
    {
        $this->authorizeRecord($request, 'collections.plan.reschedule.request', $plan);
        $open = $plan->items()->get()->filter(fn ($i) => $i->status !== 'rescheduled' && Money::isPositive($i->principalOutstanding()) && ! Money::isPositive($i->feeOutstanding()));

        return Ajax::success(null, ['title' => 'Reschedule '.$plan->plan_no, 'html' => view('collections.plans._reschedule', [
            'plan' => $plan, 'open' => $open, 'total' => Money::add('0', ...$open->map(fn ($i) => $i->principalOutstanding())->all()),
        ])->render()]);
    }

    public function requestReschedule(Request $request, InstallmentPlan $plan): JsonResponse
    {
        $this->authorizeRecord($request, 'collections.plan.reschedule.request', $plan);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'max:240'],
            'lines.*.due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'lines.*.amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
        ]);
        $r = $this->service->requestReschedule($plan, $data['lines'], $data['reason'], $request->user());

        return Ajax::success('Reschedule '.$r->reschedule_no.' sent for approval.');
    }

    public function decideReschedule(Request $request, InstallmentPlan $plan, InstallmentReschedule $reschedule, string $action): JsonResponse
    {
        $this->authorizeRecord($request, 'collections.plan.reschedule.approve', $plan);
        abort_unless($reschedule->plan_id === $plan->id, 404);
        if ($action === 'approve') {
            $this->service->approveReschedule($reschedule, $request->user());

            return Ajax::success('New schedule applied.');
        }
        $this->service->rejectReschedule($reschedule, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Reschedule rejected.');
    }

    public function rescheduleRejectForm(Request $request, InstallmentPlan $plan, InstallmentReschedule $reschedule): JsonResponse
    {
        $this->authorizeRecord($request, 'collections.plan.reschedule.approve', $plan);
        abort_unless($reschedule->plan_id === $plan->id, 404);

        return Ajax::success(null, ['title' => 'Reject '.$reschedule->reschedule_no, 'html' => view('partials.reason-form', [
            'action' => route('installment-plans.reschedules.decide', [$plan, $reschedule, 'reject']), 'button' => 'Reject', 'reload' => true,
        ])->render()]);
    }

    public function document(Request $request, InstallmentPlan $plan): Response
    {
        $this->authorizeRecord($request, 'collections.plan.view', $plan);
        $plan->load(['sale.flat', 'sale.project', 'customer', 'items']);
        $c = $plan->customer;

        return Document::make('Payment Schedule', $plan->plan_no)->stamp(InstallmentPlan::STATUSES[$plan->status])
            ->parties(['Customer' => [$c->full_name, $c->customer_code, Phone::display($c->primary_mobile)], 'Unit' => [$plan->sale->project->name, 'Unit '.$plan->sale->flat->flat_no, 'Sale '.$plan->sale->sale_no]])
            ->meta(['Contract Value' => Format::money($plan->contract_value), 'Booking Money Received' => Format::money($plan->booking_credit)])
            ->lines(['Due Date' => 'text', 'Type' => 'text', 'Principal' => 'money', 'Late Fee' => 'money', 'Paid' => 'money', 'Outstanding' => 'money', 'Status' => 'text'],
                $plan->items->map(fn ($i) => [$i->due_date, $i->kind === 'down_payment' ? 'Down payment' : 'Installment', $i->principal_due, (float) $i->fee_due ? $i->fee_due : null,
                    Money::add($i->principal_paid, $i->fee_paid), $i->outstanding(), InstallmentScheduleItem::STATUSES[$i->status]]))
            ->summary(['Principal' => $plan->principal_amount])
            ->signatures(['Customer', 'Collections', 'Authorised signatory'])
            ->respond($request);
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('collections.plan.view'), 403);
        $query = InstallmentPlan::query()->visibleTo($request->user())->with(['sale:id,public_id,sale_no,flat_id,project_id', 'sale.flat:id,flat_no', 'sale.project:id,code', 'customer:id,public_id,full_name'])->withCount('items');
        if (array_key_exists($status = (string) $request->input('status'), InstallmentPlan::STATUSES)) {
            $query->where('status', $status);
        }
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->whereHas('sale', fn ($q) => $q->where('project_id', $project->id));
        }

        return TableQuery::prepare($query, $request, ['plan_no', 'customer.full_name', 'sale.sale_no'], ['date' => 'start_date', 'principal' => 'principal_amount'], 'date', 'desc');
    }

    /** @return array{0: FlatSale, 1: array} */
    private function input(Request $request): array
    {
        $request->merge(['down_payment_amount' => str_replace(',', '', (string) ($request->input('down_payment_amount') ?: '0'))]);
        $data = $request->validate([
            'sale' => ['required', 'string', 'size:16'],
            'start_date' => ['required', 'date'],
            'down_payment_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'installment_frequency' => ['required', Rule::in(array_keys(InstallmentPlan::FREQUENCIES))],
            'count' => ['nullable', 'required_unless:installment_frequency,custom', 'integer', 'min:1', 'max:240'],
            'first_due_date' => ['nullable', 'required_unless:installment_frequency,custom', 'date', 'after_or_equal:start_date'],
            'lines' => ['nullable', 'array', 'max:240'],
            'lines.*.due_date' => ['nullable', 'date'],
            'lines.*.amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
        ]);
        $sale = FlatSale::query()->visibleTo($request->user())->where('public_id', $data['sale'])->first()
            ?? throw ValidationException::withMessages(['sale' => 'Select a sale you can access.']);
        $data['down_payment_amount'] = (string) $data['down_payment_amount'];

        return [$sale, $data];
    }
}
