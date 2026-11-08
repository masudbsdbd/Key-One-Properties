<?php

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\Project;
use App\Modules\Sales\Controllers\Concerns\AuthorizesProjectRecords;
use App\Modules\Sales\Services\SalesService;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

class BookingController extends Controller
{
    use AuthorizesProjectRecords;

    public function __construct(private SalesService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('sales.booking.view'), 403);

        return view('sales.bookings.index');
    }

    public function table(Request $request): JsonResponse
    {
        $bookings = TableQuery::paginate($this->listing($request), $request);

        return Ajax::table(view('sales.bookings._rows', ['bookings' => $bookings])->render(), $bookings);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('sales.export'), 403);

        return Report::make('Bookings')->meta(['Status' => FlatBooking::STATUSES[(string) $request->input('status')] ?? null, 'Project' => Project::findByPublicId($request->input('project'))?->name])
            ->columns(['Booking No' => 'text', 'Date' => 'date', 'Project' => 'text', 'Unit' => 'text', 'Customer' => 'text', 'Agreed Price' => 'money', 'Booking Money' => 'money', 'Status' => 'text'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (FlatBooking $b) => [$b->booking_no, $b->booking_date, $b->project->code, $b->flat->flat_no, $b->customer->full_name,
                $b->agreed_price, $b->booking_amount_expected, FlatBooking::STATUSES[$b->status]]))
            ->respond($request);
    }

    /** Available units of projects the user can see. */
    public function flatOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['sales.booking.create', 'sales.sale.create']), 403);
        $query = Flat::query()->visibleTo($request->user())->where('status', 'available')->doesntHave('claim')->with(['project:id,code', 'building:id,code'])->orderBy('project_id')->orderBy('flat_no');
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }

        [$results, $more] = TableQuery::options($query, $request, ['flat_no'],
            fn (Flat $f) => ['id' => $f->public_id, 'text' => $f->project->code.' · '.$f->building->code.' · Unit '.$f->flat_no]);

        return Ajax::options($results, $more);
    }

    /** Price card for the selected unit in the booking and sale forms. */
    public function flatSummary(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['sales.booking.create', 'sales.sale.create']), 403);
        $flat = Flat::query()->visibleTo($request->user())->where('public_id', (string) $request->input('flat'))->with(['project', 'building', 'floor', 'claim.claimant', 'priceHistory' => fn ($q) => $q->latest('id')->limit(5)])->firstOrFail();

        return Ajax::success(null, ['html' => view('sales._flat-card', ['flat' => $flat])->render()]);
    }

    public function create(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.booking.create'), 403);
        $booking = new FlatBooking(['booking_date' => now(config('app.display_timezone'))]);
        $booking->setRelation('flat', Flat::query()->visibleTo($request->user())->where('public_id', (string) $request->input('flat'))->first());
        $booking->setRelation('customer', null);

        return $this->form($booking, 'New Booking');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sales.booking.create'), 403);
        [$flat, $customer, $data] = $this->input($request);
        $booking = $this->service->saveBooking(null, $flat, $customer, $data, $request->user());
        if ($request->boolean('submit')) {
            abort_unless($request->user()->can('sales.booking.submit'), 403);
            $this->service->submitBooking($booking, $request->user());
        }

        return Ajax::success('Booking '.$booking->booking_no.' saved.', ['redirect' => route('bookings.show', $booking)]);
    }

    public function show(Request $request, FlatBooking $booking): View
    {
        $this->authorizeRecord($request, 'sales.booking.view', $booking);
        $booking->load(['flat.building', 'flat.floor', 'customer', 'project', 'creator', 'approver', 'sale', 'cancellations.requester', 'payments' => fn ($q) => $q->latest('id')]);

        return view('sales.bookings.show', [
            'booking' => $booking,
            'refundable' => SalesService::refundable($booking, null),
            'activity' => Activity::query()->where('subject_type', $booking->getMorphClass())->where('subject_id', $booking->id)->with('causer')->latest()->limit(50)->get(),
        ]);
    }

    public function edit(Request $request, FlatBooking $booking): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.booking.edit', $booking);

        return $this->form($booking->load(['flat.project', 'flat.building', 'customer']), 'Edit '.$booking->booking_no);
    }

    public function update(Request $request, FlatBooking $booking): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.booking.edit', $booking);
        [$flat, $customer, $data] = $this->input($request);
        $this->service->saveBooking($booking, $flat, $customer, $data, $request->user());

        return Ajax::success('Booking updated.');
    }

    public function submit(Request $request, FlatBooking $booking): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.booking.submit', $booking);
        $this->service->submitBooking($booking, $request->user());

        return Ajax::success('Booking sent for approval.');
    }

    public function approve(Request $request, FlatBooking $booking): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.booking.approve', $booking);
        $this->service->approveBooking($booking, $request->user());

        return Ajax::success('Booking confirmed. Unit '.$booking->flat->flat_no.' is booked.');
    }

    public function reasonForm(Request $request, FlatBooking $booking, string $action): JsonResponse
    {
        $this->authorizeRecord($request, $action === 'reject' ? 'sales.booking.approve' : 'sales.booking.edit', $booking);

        return Ajax::success(null, ['title' => Str::headline($action).' '.$booking->booking_no, 'html' => view('partials.reason-form', [
            'action' => route('bookings.'.$action, $booking), 'button' => Str::headline($action), 'reload' => true,
        ])->render()]);
    }

    public function reject(Request $request, FlatBooking $booking): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.booking.approve', $booking);
        $this->service->rejectBooking($booking, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Booking rejected.');
    }

    public function withdraw(Request $request, FlatBooking $booking): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.booking.edit', $booking);
        $this->service->withdrawBooking($booking, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'], $request->user());

        return Ajax::success('Booking withdrawn.');
    }

    public function cancelForm(Request $request, FlatBooking $booking): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.booking.cancel.request', $booking);

        return Ajax::success(null, ['title' => 'Cancel '.$booking->booking_no, 'html' => view('sales._cancel-form', [
            'action' => route('bookings.cancel', $booking), 'refundable' => SalesService::refundable($booking, null),
        ])->render()]);
    }

    public function requestCancel(Request $request, FlatBooking $booking): JsonResponse
    {
        $this->authorizeRecord($request, 'sales.booking.cancel.request', $booking);
        $data = $this->cancelInput($request);
        $c = $this->service->requestCancellation($booking, null, $data['reason'], $data['refund_expected'], $request->user());

        return Ajax::success('Cancellation '.$c->cancellation_no.' sent for approval.');
    }

    private function listing(Request $request): Builder
    {
        abort_unless($request->user()->can('sales.booking.view'), 403);
        $query = FlatBooking::query()->visibleTo($request->user())->with(['flat:id,public_id,flat_no,building_id', 'flat.building:id,code', 'customer:id,public_id,full_name,customer_code', 'project:id,code']);
        if (array_key_exists($status = (string) $request->input('status'), FlatBooking::STATUSES)) {
            $query->where('status', $status);
        }
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->where('project_id', $project->id);
        }
        if ($customer = Customer::findByPublicId($request->input('customer'))) {
            $query->where('customer_id', $customer->id);
        }

        return TableQuery::prepare($query, $request, ['booking_no', 'flat.flat_no', 'customer.full_name'], ['date' => 'booking_date', 'price' => 'agreed_price'], 'date', 'desc');
    }

    /** @return array{0: Flat, 1: Customer, 2: array} */
    private function input(Request $request): array
    {
        foreach (['agreed_price', 'booking_amount_expected'] as $f) {
            $request->merge([$f => str_replace(',', '', (string) $request->input($f))]);
        }
        $data = $request->validate([
            'flat' => ['required', 'string', 'size:16'],
            'customer' => ['required', 'string', 'size:16'],
            'booking_date' => ['required', 'date', 'before_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'booking_expires_at' => ['nullable', 'date', 'after_or_equal:booking_date'],
            'agreed_price' => ['required', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
            'booking_amount_expected' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $flat = Flat::query()->visibleTo($request->user())->where('public_id', $data['flat'])->first()
            ?? throw ValidationException::withMessages(['flat' => 'Select a unit you can access.']);
        $customer = Customer::query()->visibleTo($request->user())->where('public_id', $data['customer'])->first()
            ?? throw ValidationException::withMessages(['customer' => 'Select a customer you can access.']);
        $data['booking_amount_expected'] = (string) ($data['booking_amount_expected'] ?? '0');
        $data['agreed_price'] = (string) $data['agreed_price'];

        return [$flat, $customer, $data];
    }

    private function cancelInput(Request $request): array
    {
        $request->merge(['refund_expected' => str_replace(',', '', (string) ($request->input('refund_expected') ?: '0'))]);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'refund_expected' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ]);
        $data['refund_expected'] = (string) $data['refund_expected'];

        return $data;
    }

    private function form(FlatBooking $booking, string $title): JsonResponse
    {
        return Ajax::success(null, ['title' => $title, 'html' => view('sales.bookings._form', ['booking' => $booking])->render()]);
    }
}
