<?php

namespace App\Modules\Collections\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLateFeePolicy;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\LateFeeAssessment;
use App\Modules\Collections\Services\LateFeeService;
use App\Support\Ajax;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LateFeeController extends Controller
{
    public function __construct(private LateFeeService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('collections.latefee.view'), 403);

        return view('collections.late-fees.index');
    }

    public function policies(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('collections.latefee.view'), 403);
        $query = CustomerLateFeePolicy::query()->whereIn('customer_id', Customer::query()->visibleTo($request->user())->select('id'))->with(['customer:id,public_id,full_name,customer_code', 'sale:id,public_id,sale_no,project_id']);
        $rows = TableQuery::apply($query, $request, ['customer.full_name'], ['from' => 'effective_from'], 'from', 'desc');

        return Ajax::table(view('collections.late-fees._policies', ['policies' => $rows])->render(), $rows);
    }

    public function assessments(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('collections.latefee.view'), 403);
        $plans = InstallmentPlan::query()->visibleTo($request->user())->select('id');
        $query = LateFeeAssessment::query()->whereHas('item', fn ($q) => $q->whereIn('plan_id', $plans))->with(['item.plan.customer', 'item.plan.sale.flat']);
        if (array_key_exists($status = (string) $request->input('status'), LateFeeAssessment::STATUSES)) {
            $query->where('status', $status);
        }
        $rows = TableQuery::apply($query, $request, [], ['date' => 'assessed_on'], 'date', 'desc');

        return Ajax::table(view('collections.late-fees._assessments', ['assessments' => $rows])->render(), $rows);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('collections.latefee.manage'), 403);
        $customer = Customer::query()->visibleTo($request->user())->where('public_id', (string) $request->input('customer'))->first();

        return Ajax::success(null, ['title' => 'Late-Fee Policy', 'html' => view('collections.late-fees._form', ['customer' => $customer])->render()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('collections.latefee.manage'), 403);
        $data = $request->validate([
            'customer' => ['required', 'string', 'size:16'],
            'sale' => ['nullable', 'string', 'size:16'],
            'fee_type' => ['required', Rule::in(array_keys(CustomerLateFeePolicy::TYPES))],
            'fee_value' => ['nullable', 'required_unless:fee_type,none', 'numeric', 'min:0', 'max:99999999', 'decimal:0,6'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'frequency' => ['required', Rule::in(array_keys(CustomerLateFeePolicy::FREQUENCIES))],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
        $customer = Customer::query()->visibleTo($request->user())->where('public_id', $data['customer'])->first()
            ?? throw ValidationException::withMessages(['customer' => 'Select a customer you can access.']);
        $sale = ! empty($data['sale']) ? (FlatSale::query()->visibleTo($request->user())->where('customer_id', $customer->id)->where('public_id', $data['sale'])->first()
            ?? throw ValidationException::withMessages(['sale' => 'Select a sale of this customer.'])) : null;
        $data['fee_value'] = (string) ($data['fee_value'] ?? '0');
        $this->service->savePolicy(null, $customer, $sale, $data, $request->user());

        return Ajax::success('Late-fee policy saved.');
    }

    public function waiveForm(Request $request, LateFeeAssessment $assessment): JsonResponse
    {
        $this->authorizeWaive($request, $assessment);

        return Ajax::success(null, ['title' => 'Waive Late Fee', 'html' => view('partials.reason-form', [
            'action' => route('late-fees.waive', $assessment), 'button' => 'Waive', 'reload' => true, 'tone' => 'neutral',
        ])->render()]);
    }

    public function waive(Request $request, LateFeeAssessment $assessment): JsonResponse
    {
        $this->authorizeWaive($request, $assessment);
        $this->service->waive($assessment, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Late fee waived.');
    }

    /** Sales of one customer for the policy form. */
    public function saleOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('collections.latefee.manage'), 403);
        $customer = Customer::query()->visibleTo($request->user())->where('public_id', (string) $request->input('customer'))->first();
        if (! $customer) {
            return Ajax::options([]);
        }
        [$results, $more] = TableQuery::options(FlatSale::query()->visibleTo($request->user())->where('customer_id', $customer->id)->with('flat:id,flat_no'), $request, ['sale_no'],
            fn (FlatSale $s) => ['id' => $s->public_id, 'text' => $s->sale_no.' · Unit '.$s->flat->flat_no]);

        return Ajax::options($results, $more);
    }

    private function authorizeWaive(Request $request, LateFeeAssessment $assessment): void
    {
        abort_unless($request->user()->can('collections.latefee.waive') && $assessment->item->plan->isVisibleTo($request->user()), 403);
    }
}
