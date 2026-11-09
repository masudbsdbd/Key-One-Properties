<?php

namespace App\Modules\Customers\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\FlatSale;
use App\Models\User;
use App\Modules\Customers\Requests\CustomerRequest;
use App\Modules\Customers\Services\CustomerService;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\ListSummary;
use App\Support\Phone;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $service) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Customer::class);

        return view('customers.index');
    }

    public function table(Request $request): JsonResponse
    {
        $query = $this->listing($request);
        $customers = TableQuery::paginate($query, $request);

        return Ajax::table(view('customers._rows', ['customers' => $customers])->render(), $customers, ['stats' => $this->summary($request)->render()]);
    }

    public function export(Request $request): Response
    {
        abort_unless($request->user()->can('customers.export'), 403);

        return Report::make('Customers')->meta(['Status' => Customer::STATUSES[(string) $request->input('status')] ?? null, 'Source' => Customer::SOURCES[(string) $request->input('source')] ?? null])
            ->columns(['Code' => 'text', 'Name' => 'text', 'Mobile' => 'text', 'Email' => 'text', 'Source' => 'text', 'Status' => 'text', 'Created' => 'date'])
            ->rows(fn () => $this->listing($request)->lazy()->map(fn (Customer $c) => [$c->customer_code, $c->full_name, Phone::display($c->primary_mobile), $c->email, Customer::SOURCES[$c->source] ?? null, Customer::STATUSES[$c->status], $c->created_at]))
            ->respond($request);
    }

    private function summary(Request $request): ListSummary
    {
        $user = $request->user();
        $active = ListSummary::marks(FlatSale::ACTIVE);
        $today = now(config('app.display_timezone'))->toDateString();
        $row = ListSummary::aggregate($this->listing(ListSummary::without($request, ['dues'])), [
            'all' => 'COUNT(*)',
            'buyers' => ["SUM(CASE WHEN EXISTS (SELECT 1 FROM flat_sales s WHERE s.customer_id = customers.id AND s.status IN ({$active})) THEN 1 ELSE 0 END)", FlatSale::ACTIVE],
            'receivable' => 'SUM((SELECT COALESCE(SUM(e.charge_amount - e.credit_amount), 0) FROM customer_statement_entries e WHERE e.customer_id = customers.id))',
            'overdue' => ["SUM(CASE WHEN EXISTS (SELECT 1 FROM installment_schedule_items i JOIN installment_plans pl ON pl.id = i.plan_id WHERE pl.customer_id = customers.id AND pl.status = 'active' AND i.status NOT IN ('paid', 'rescheduled') AND i.due_date < ? AND i.principal_due + i.fee_due - i.principal_paid - i.fee_paid > 0) THEN 1 ELSE 0 END)", [$today]],
        ]);

        return ListSummary::make($request)
            ->count('Customers', $row->all, 'users', 'info')
            ->count('Buyers', $row->buyers, 'door', 'success')
            ->when($user->can('collections.payment.view'), fn ($s) => $s->money('Receivable', $row->receivable, 'tag', 'warning'))
            ->when($user->can('collections.plan.view'), fn ($s) => $s->count('With Overdue Dues', $row->overdue, 'alert', 'danger', ['dues' => 'overdue']));
    }

    private function listing(Request $request): Builder
    {
        Gate::authorize('viewAny', Customer::class);
        $user = $request->user();

        $query = Customer::query()->visibleTo($user)->with('creator:id,name');

        if ($request->input('archived') === '1') {
            abort_unless($user->can('customers.archive'), 403);
            $query->onlyTrashed();
        }
        if (array_key_exists($status = (string) $request->input('status'), Customer::STATUSES)) {
            $query->where('status', $status);
        }
        if (array_key_exists($source = (string) $request->input('source'), Customer::SOURCES)) {
            $query->where('source', $source);
        }
        if ($user->can('customers.view_all') && ($owner = User::findByPublicId($request->input('owner')))) {
            $query->where('created_by', $owner->id);
        }

        if ($request->input('dues') === 'overdue' && $user->can('collections.plan.view')) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('installment_schedule_items as i')->join('installment_plans as pl', 'pl.id', '=', 'i.plan_id')
                ->whereColumn('pl.customer_id', 'customers.id')->where('pl.status', 'active')->whereNotIn('i.status', ['paid', 'rescheduled'])
                ->whereDate('i.due_date', '<', now(config('app.display_timezone'))->toDateString())->whereRaw('i.principal_due + i.fee_due - i.principal_paid - i.fee_paid > 0'));
        }

        // Mobile searches match the normalised number too.
        if (($term = trim((string) $request->input('search'))) !== '' && preg_match('/^[\d\s+\-]{6,}$/', $term)) {
            $request->merge(['search' => Phone::normalize($term)]);
        }

        return TableQuery::prepare($query, $request, ['full_name', 'customer_code', 'primary_mobile', 'alternate_mobile', 'email'], [
            'name' => 'full_name',
            'code' => 'customer_code',
            'created' => 'created_at',
        ], 'created', 'desc');
    }

    public function create(): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        return $this->form(new Customer(['status' => 'active']), 'New Customer');
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        $warnings = $request->possibleDuplicates();
        $customer = $this->service->create($request->validated(), $request->user());

        return Ajax::success(
            $warnings ? 'Customer created. Possible duplicate: '.implode(', ', $warnings) : 'Customer created.',
            ['redirect' => route('customers.show', $customer)],
        );
    }

    public function show(Customer $customer): View
    {
        Gate::authorize('view', $customer);
        $customer->load(['creator', 'addresses']);

        return view('customers.show', ['customer' => $customer]);
    }

    public function edit(Customer $customer): JsonResponse
    {
        Gate::authorize('update', $customer);

        return $this->form($customer, 'Edit Customer');
    }

    public function update(CustomerRequest $request, Customer $customer): JsonResponse
    {
        $data = $request->validated();
        if (! $request->user()->can('viewSensitive', $customer)) {
            unset($data['nid_or_passport']); // the field is hidden for this user
        }

        $warnings = $request->possibleDuplicates();
        $this->service->update($customer, $data, $request->user());

        return Ajax::success($warnings ? 'Customer updated. Possible duplicate: '.implode(', ', $warnings) : 'Customer updated.');
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('archive', $customer);
        $customer->delete();

        return Ajax::success('Customer archived.', ['redirect' => route('customers.index')]);
    }

    public function restore(Request $request, string $customer): JsonResponse
    {
        $model = Customer::onlyTrashed()->where('public_id', $customer)->firstOrFail();
        Gate::authorize('archive', $model);
        $model->restore();

        return Ajax::success('Customer restored.');
    }

    public function identity(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('viewSensitive', $customer);
        CustomerService::log($customer, $request->user(), 'identity_viewed');

        return Ajax::success(null, ['value' => $customer->nid_or_passport ?? '']);
    }

    public function activity(Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);

        $entries = Activity::query()->where('subject_type', $customer->getMorphClass())->where('subject_id', $customer->id)
            ->with('causer')->latest()->latest('id')->limit(100)->get();

        return Ajax::success(null, ['html' => view('projects.partials._activity', ['entries' => $entries])->render()]);
    }

    private function form(Customer $customer, string $title): JsonResponse
    {
        return Ajax::success(null, [
            'title' => $title,
            'html' => view('customers._form', ['customer' => $customer])->render(),
        ]);
    }
}
