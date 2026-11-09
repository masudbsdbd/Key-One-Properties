<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\FlatHandover;
use App\Models\FlatRegistration;
use App\Models\FlatSale;
use App\Models\Project;
use App\Modules\Collections\Services\CustomerStatement;
use App\Modules\Sales\Controllers\Concerns\AuthorizesProjectRecords;
use App\Modules\Sales\Services\SalesService;
use App\Support\Ajax;
use App\Support\Export\Document;
use App\Support\Export\Report;
use App\Support\Phone;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

class SaleController extends Controller
{
    use AuthorizesProjectRecords;

    public function __construct(private SalesService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('sales.sale.view'), 403);

        return view('sales.sales.index');
    }

    public function table(Request $request): JsonResponse
    {
        $sales = TableQuery::paginate($this->listing($request), $request);

        return Ajax::table(view('sales.sales._rows', ['sales' => $sales])->render(), $sales);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('sales.export'), 403);

        return Report::make('Sales')->meta(['Status' => FlatSale::STATUSES[(string) $request->input('status')] ?? null, 'Project' => Project::findByPublicId($request->input('project'))?->name])
            ->columns(['Sale No' => 'text', 'Date' => 'date', 'Project' => 'text', 'Unit' => 'text', 'Customer' => 'text', 'Price' => 'money', 'Discount' => 'money', 'Contract Value' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (FlatSale $s) => [$s->sale_no, $s->agreement_date, $s->project->code, $s->flat->flat_no, $s->customer->full_name,
                $s->sale_price, $s->discount_amount, $s->net_contract_value, FlatSale::STATUSES[$s->status]]))
            ->respond($request);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.sale.create'), 403);
        $booking = FlatBooking::query()->visibleTo($request->user())->where('public_id', (string) $request->input('booking'))->with(['flat.project', 'flat.building', 'customer'])->first();
        $sale = new FlatSale(['agreement_date' => now(config('app.display_timezone')), 'sale_price' => $booking?->agreed_price, 'discount_amount' => 0]);
        $sale->setRelation('booking', $booking);
        $sale->setRelation('flat', $booking?->flat);
        $sale->setRelation('customer', $booking?->customer);

        return $this->form($sale, $booking ? 'Convert '.$booking->booking_no.' to Sale' : 'New Sale');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.sale.create'), 403);
        [$flat, $customer, $booking, $data] = $this->input($request, null);
        $sale = $this->service->saveSale(null, $flat, $customer, $booking, $data, $request->user());
        if ($request->boolean('submit')) {
            $this->service->submitSale($sale, $request->user());
        }

        return Ajax::success('Sale '.$sale->sale_no.' saved.', ['redirect' => route('sales.show', $sale)]);
    }

    public function show(Request $request, FlatSale $sale): View
    {
        $this->authorizeRecord($request, 'sales.sale.view', $sale);
        $sale->load(['flat.building', 'flat.floor', 'customer', 'project', 'booking', 'agreement', 'registration', 'handover', 'creator', 'approver',
            'cancellations.requester', 'plans', 'payments' => fn ($q) => $q->latest('id')]);

        return view('sales.sales.show', [
            'sale' => $sale,
            'balance' => in_array($sale->status, FlatSale::ACTIVE, true) ? CustomerStatement::balance($sale->customer) : null,
            'refundable' => SalesService::refundable(null, $sale),
            'activity' => Activity::query()->where('subject_type', $sale->getMorphClass())->where('subject_id', $sale->id)->with('causer')->latest()->limit(50)->get(),
        ]);
    }

    public function edit(Request $request, FlatSale $sale): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.sale.create', $sale);

        return $this->form($sale->load(['flat.project', 'flat.building', 'customer', 'booking']), 'Edit '.$sale->sale_no);
    }

    public function update(Request $request, FlatSale $sale): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.sale.create', $sale);
        [$flat, $customer, $booking, $data] = $this->input($request, $sale);
        $this->service->saveSale($sale, $flat, $customer, $booking, $data, $request->user());

        return Ajax::success('Sale updated.');
    }

    public function submit(Request $request, FlatSale $sale): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.sale.create', $sale);
        $this->service->submitSale($sale, $request->user());

        return Ajax::success('Sale sent for approval.');
    }

    public function approve(Request $request, FlatSale $sale): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.sale.approve', $sale);
        $this->service->approveSale($sale, $request->user());

        return Ajax::success('Sale approved. Unit '.$sale->flat->flat_no.' is sold.');
    }

    public function rejectForm(Request $request, FlatSale $sale): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.sale.approve', $sale);

        return Ajax::success(null, ['title' => 'Reject '.$sale->sale_no, 'html' => view('partials.reason-form', ['action' => route('sales.reject', $sale), 'button' => 'Reject', 'reload' => true])->render()]);
    }

    public function reject(Request $request, FlatSale $sale): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.sale.approve', $sale);
        $this->service->rejectSale($sale, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Sale rejected.');
    }

    public function stageForm(Request $request, FlatSale $sale, string $stage): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.sale.'.$stage.'.manage', $sale);
        $sale->load(['agreement', 'registration', 'handover']);

        return Ajax::success(null, ['title' => ucfirst($stage).' · '.$sale->sale_no, 'html' => view('sales.sales._'.$stage, ['sale' => $sale])->render()]);
    }

    public function updateStage(Request $request, FlatSale $sale, string $stage): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.sale.'.$stage.'.manage', $sale);
        $today = now(config('app.display_timezone'))->toDateString();
        match ($stage) {
            'agreement' => $this->service->updateAgreement($sale, $request->validate([
                'signed_at' => ['nullable', 'date', 'before_or_equal:'.$today], 'notes' => ['nullable', 'string', 'max:2000'],
            ]), $request->user()),
            'registration' => $this->service->updateRegistration($sale, $request->merge(['registration_cost' => str_replace(',', '', (string) $request->input('registration_cost'))])->validate([
                'status' => ['required', Rule::in(array_keys(FlatRegistration::STATUSES))], 'registration_no' => ['nullable', 'string', 'max:60'],
                'registered_at' => ['nullable', 'date', 'before_or_equal:'.$today], 'registration_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'], 'notes' => ['nullable', 'string', 'max:2000'],
            ]), $request->user()),
            'handover' => $this->service->updateHandover($sale, $request->validate([
                'status' => ['required', Rule::in(array_keys(FlatHandover::STATUSES))], 'scheduled_at' => ['nullable', 'date'], 'completed_at' => ['nullable', 'date', 'before_or_equal:'.$today],
                'checklist' => ['nullable', 'array'], 'checklist.*' => ['string'], 'notes' => ['nullable', 'string', 'max:2000'],
            ]), $request->user()),
        };

        return Ajax::success(ucfirst($stage).' updated.');
    }

    public function cancelForm(Request $request, FlatSale $sale): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.booking.cancel.request', $sale);

        return Ajax::success(null, ['title' => 'Cancel '.$sale->sale_no, 'html' => view('sales._cancel-form', [
            'action' => route('sales.cancel', $sale), 'refundable' => SalesService::refundable(null, $sale),
        ])->render()]);
    }

    public function requestCancel(Request $request, FlatSale $sale): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.booking.cancel.request', $sale);
        $request->merge(['refund_expected' => str_replace(',', '', (string) ($request->input('refund_expected') ?: '0'))]);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255'], 'refund_expected' => ['required', 'numeric', 'min:0', 'decimal:0,2']]);
        $c = $this->service->requestCancellation(null, $sale, $data['reason'], (string) $data['refund_expected'], $request->user());

        return Ajax::success('Cancellation '.$c->cancellation_no.' sent for approval.');
    }

    public function document(Request $request, FlatSale $sale): Response
    {
        $this->authorizeRecord($request, 'sales.sale.view', $sale);
        $sale->load(['flat.building', 'flat.floor', 'customer', 'project', 'booking', 'agreement', 'creator', 'approver']);
        $c = $sale->customer;

        return Document::make('Sale Agreement Summary', $sale->sale_no)->stamp(FlatSale::STATUSES[$sale->status])
            ->parties(['Purchaser' => [$c->full_name, $c->customer_code, Phone::display($c->primary_mobile), $c->email], 'Unit' => [$sale->project->name, $sale->flat->building->name.' · '.$sale->flat->floor->label, 'Unit '.$sale->flat->flat_no.' · '.$sale->flat->flat_type, number_format((float) $sale->flat->saleable_area).' '.$sale->flat->area_unit]])
            ->meta(['Agreement Date' => $sale->agreement_date, 'Agreement No' => $sale->agreement?->agreement_no, 'Booking' => $sale->booking?->booking_no, 'Signed' => $sale->agreement?->signed_at, 'Prepared By' => $sale->creator->name, 'Approved By' => $sale->approver?->name])
            ->summary(['Sale Price' => $sale->sale_price, 'Discount' => (float) $sale->discount_amount ? '-'.$sale->discount_amount : null, 'Contract Value' => $sale->net_contract_value])
            ->notes($sale->notes)
            ->signatures(['Purchaser', 'Sales', 'Authorised signatory'])
            ->respond($request);
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('sales.sale.view'), 403);
        $query = FlatSale::query()->visibleTo($request->user())->with(['flat:id,public_id,flat_no,project_id', 'customer:id,public_id,full_name,customer_code,created_by', 'project:id,public_id,code']);
        if (array_key_exists($status = (string) $request->input('status'), FlatSale::STATUSES)) {
            $query->where('status', $status);
        }
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }
        if ($customer = Customer::findByPublicId($request->input('customer'))) {
            $query->where('customer_id', $customer->id);
        }

        return TableQuery::prepare($query, $request, ['sale_no', 'flat.flat_no', 'customer.full_name'], ['date' => 'agreement_date', 'value' => 'net_contract_value'], 'date', 'desc');
    }

    /** @return array{0: ?Flat, 1: ?Customer, 2: ?FlatBooking, 3: array} */
    private function input(Request $request, ?FlatSale $sale): array
    {
        foreach (['sale_price', 'discount_amount'] as $f) {
            $request->merge([$f => str_replace(',', '', (string) $request->input($f))]);
        }
        $data = $request->validate([
            'booking' => ['nullable', 'string', 'size:16'],
            'flat' => ['nullable', 'required_without:booking', 'string', 'size:16'],
            'customer' => ['nullable', 'required_without:booking', 'string', 'size:16'],
            'agreement_date' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'sale_price' => ['required', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $booking = $sale?->booking ?? (! empty($data['booking']) ? (FlatBooking::query()->visibleTo($request->user())->where('public_id', $data['booking'])->first()
            ?? throw ValidationException::withMessages(['booking' => 'Select a booking you can access.'])) : null);
        $flat = $booking?->flat ?? (Flat::query()->visibleTo($request->user())->where('public_id', (string) ($data['flat'] ?? ''))->first()
            ?? throw ValidationException::withMessages(['flat' => 'Select a unit you can access.']));
        $customer = $booking?->customer ?? (Customer::query()->visibleTo($request->user())->where('public_id', (string) ($data['customer'] ?? ''))->first()
            ?? throw ValidationException::withMessages(['customer' => 'Select a customer you can access.']));
        $data['sale_price'] = (string) $data['sale_price'];
        $data['discount_amount'] = (string) ($data['discount_amount'] ?? '0');

        return [$flat, $customer, $booking, $data];
    }

    private function form(FlatSale $sale, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('sales.sales._form', ['sale' => $sale])->render()]);
    }
}
