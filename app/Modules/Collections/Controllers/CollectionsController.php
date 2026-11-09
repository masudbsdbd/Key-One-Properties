<?php

namespace App\Modules\Collections\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerStatementEntry;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\PaymentAllocation;
use App\Models\Project;
use App\Support\Ajax;
use App\Support\Export\Report;
use App\Support\Format;
use App\Support\ListSummary;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Collections dashboard, dues and overdue aging, and the customer statement.
 */
class CollectionsController extends Controller
{
    public function overview(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('collections.payment.view'), 403);
        $today = now(config('app.display_timezone'))->startOfDay();
        $items = $this->openItems($request);
        $open = (clone $items)->get();
        $outstanding = fn ($c) => Money::add('0', ...$c->map(fn ($i) => $i->outstanding())->all());
        $payments = CustomerPayment::query()->visibleTo($user);

        return view('collections.overview', [
            'overdue' => $outstanding($open->filter(fn ($i) => $i->due_date->lt($today))),
            'dueThisMonth' => $outstanding($open->filter(fn ($i) => $i->due_date->between($today->copy()->startOfMonth(), $today->copy()->endOfMonth()))),
            'collectedThisMonth' => Money::amount((string) (clone $payments)->where('status', 'posted')->whereBetween('payment_date', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])->sum('amount')),
            'pendingPayments' => (clone $payments)->where('status', 'pending')->count(),
            'advances' => Money::amount((string) PaymentAllocation::query()->where('allocation_type', 'unapplied_advance')->whereIn('payment_id', (clone $payments)->where('status', 'posted')->select('id'))->sum('amount')),
            'aging' => $this->aging($open, $today),
            'recent' => (clone $payments)->with(['customer', 'sale.flat', 'booking.flat'])->latest('id')->limit(8)->get(),
        ]);
    }

    public function dues(Request $request): View
    {
        abort_unless($request->user()->can('collections.plan.view'), 403);

        return view('collections.dues.index', ['aging' => $this->aging($this->openItems($request)->get(), now(config('app.display_timezone'))->startOfDay())]);
    }

    public function duesTable(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('collections.plan.view'), 403);
        $all = $this->filteredDues($request);
        $page = max(1, (int) $request->input('page', 1));
        $rows = $all->forPage($page, 25)->values();

        $today = now(config('app.display_timezone'))->startOfDay();
        $open = $this->filteredDues(ListSummary::without($request, ['filter']));
        $sum = fn ($items) => Money::add('0', ...$items->map(fn ($i) => $i->outstanding())->all());
        $overdue = $open->filter(fn ($i) => $i->due_date->lt($today));
        $summary = ListSummary::make($request)
            ->money('Overdue', $sum($overdue), 'alert', 'danger', ['filter' => 'overdue'], ListSummary::many($overdue->pluck('plan.customer_id')->unique()->count(), 'customer'))
            ->money('Due This Week', $sum($open->filter(fn ($i) => $i->due_date->between($today, $today->copy()->addDays(6)))), 'clock', 'warning', ['filter' => 'week'])
            ->money('Due This Month', $sum($open->filter(fn ($i) => $i->due_date->between($today->copy()->startOfMonth(), $today->copy()->endOfMonth()))), 'calendar', 'info', ['filter' => 'month'])
            ->money('All Open Dues', $sum($open), 'tag', 'accent', note: ListSummary::many($open->pluck('plan.customer_id')->unique()->count(), 'customer'));

        return Ajax::table(view('collections.dues._rows', ['items' => $rows])->render(), new LengthAwarePaginator($rows, $all->count(), 25, $page), ['stats' => $summary->render()]);
    }

    public function duesExport(Request $request): Response
    {
        abort_unless($request->user()->can('collections.plan.view') && $request->user()->can('collections.payment.statement.export'), 403);

        return Report::make('Customer Dues')->meta(['Project' => Project::findByPublicId($request->input('project'))?->name, 'Show' => ['overdue' => 'Overdue only', 'month' => 'Due this month'][(string) $request->input('filter')] ?? null])
            ->columns(['Customer' => 'text', 'Mobile' => 'text', 'Sale' => 'text', 'Unit' => 'text', 'Installment' => 'text', 'Due Date' => 'date', 'Days Late' => 'number', 'Principal' => 'money', 'Late Fee' => 'money', 'Outstanding' => 'money'])
            ->rows(fn () => $this->filteredDues($request)->map(fn ($i) => [$i->plan->customer->full_name, Phone::display($i->plan->customer->primary_mobile), $i->plan->sale->sale_no,
                $i->plan->sale->flat->flat_no, '#'.$i->sequence_no, $i->due_date, max(0, (int) $i->due_date->diffInDays(now(config('app.display_timezone'))->startOfDay(), false)),
                $i->principalOutstanding(), $i->feeOutstanding(), $i->outstanding()]))
            ->respond($request);
    }

    public function statement(Request $request, Customer $customer): View
    {
        $this->authorizeStatement($request, $customer);

        return view('collections.statements.show', ['customer' => $customer, 'balance' => $this->statementBalance($request, $customer)]);
    }

    public function statementTable(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeStatement($request, $customer);
        $page = max(1, (int) $request->input('page', 1));
        $rows = $this->statementQuery($request, $customer)->forPage($page, 50)->get();
        $total = (clone $this->statementQuery($request, $customer))->count();

        return Ajax::table(view('collections.statements._rows', ['rows' => $rows])->render(), new LengthAwarePaginator($rows, $total, 50, $page));
    }

    public function statementExport(Request $request, Customer $customer): Response
    {
        $this->authorizeStatement($request, $customer);
        abort_unless($request->user()->can('collections.payment.statement.export'), 403);

        return Report::make('Customer Statement')
            ->meta(['Customer' => $customer->full_name.' ('.$customer->customer_code.')', 'Mobile' => Phone::display($customer->primary_mobile), 'Balance' => Format::money($this->statementBalance($request, $customer))])
            ->columns(['Date' => 'date', 'Type' => 'text', 'Description' => 'text', 'Charge' => 'money', 'Credit' => 'money', 'Balance' => 'money'])
            ->rows(fn () => $this->statementQuery($request, $customer)->lazy()->map(fn ($r) => [$r->entry_date, CustomerStatementEntry::TYPES[$r->entry_type] ?? $r->entry_type, $r->description,
                (float) $r->charge_amount ? $r->charge_amount : null, (float) $r->credit_amount ? $r->credit_amount : null, $r->running_balance]))
            ->respond($request);
    }

    private function authorizeStatement(Request $request, Customer $customer): void
    {
        $visible = Customer::query()->visibleTo($request->user())->whereKey($customer->id)->exists();
        abort_unless($request->user()->can('collections.payment.view') && $visible, 403);
    }

    /** Entries of sales and bookings in projects the user can see. */
    private function statementScope(Request $request, Customer $customer): Builder
    {
        $user = $request->user();

        return CustomerStatementEntry::query()->where('customer_id', $customer->id)->where(fn (Builder $q) => $q
            ->whereIn('sale_id', FlatSale::query()->visibleTo($user)->select('id'))
            ->orWhere(fn (Builder $b) => $b->whereNull('sale_id')->whereIn('booking_id', FlatBooking::query()->visibleTo($user)->select('id'))));
    }

    private function statementQuery(Request $request, Customer $customer): Builder
    {
        return $this->statementScope($request, $customer)->select('*')
            ->selectRaw('SUM(charge_amount - credit_amount) OVER (ORDER BY entry_date, id) AS running_balance')->orderBy('entry_date')->orderBy('id');
    }

    private function statementBalance(Request $request, Customer $customer): string
    {
        return Money::amount((string) $this->statementScope($request, $customer)->selectRaw('COALESCE(SUM(charge_amount - credit_amount),0) AS b')->toBase()->first()->b);
    }

    private function openItems(Request $request): Builder
    {
        $query = InstallmentScheduleItem::query()->whereIn('plan_id', InstallmentPlan::query()->visibleTo($request->user())->where('status', 'active')->select('id'))
            ->where('status', '!=', 'rescheduled')->whereRaw('(principal_due + fee_due) > (principal_paid + fee_paid)')
            ->with(['plan.customer', 'plan.sale.flat', 'plan.sale.project']);
        if ($project = Project::findByPublicId($request->input('project'))) {
            $query->whereHas('plan.sale', fn ($q) => $q->where('project_id', $project->id));
        }

        return $query->orderBy('due_date')->orderBy('id');
    }

    private function filteredDues(Request $request)
    {
        $today = now(config('app.display_timezone'))->startOfDay();
        $items = $this->openItems($request)->get();
        $term = mb_strtolower(trim((string) $request->input('search')));

        return $items->filter(fn ($i) => match ((string) $request->input('filter')) {
            'overdue' => $i->due_date->lt($today),
            'week' => $i->due_date->between($today, $today->copy()->addDays(6)),
            'month' => $i->due_date->between($today->copy()->startOfMonth(), $today->copy()->endOfMonth()),
            default => true,
        })->filter(fn ($i) => $term === '' || str_contains(mb_strtolower($i->plan->customer->full_name.' '.$i->plan->sale->sale_no.' '.$i->plan->sale->flat->flat_no), $term))->values();
    }

    /** @return array<string, string> */
    private function aging($items, $today): array
    {
        $buckets = ['current' => '0', '1-30' => '0', '31-60' => '0', '61-90' => '0', '90+' => '0'];
        foreach ($items as $i) {
            $days = (int) $i->due_date->diffInDays($today, false);
            $key = match (true) {
                $days <= 0 => 'current', $days <= 30 => '1-30', $days <= 60 => '31-60', $days <= 90 => '61-90', default => '90+'
            };
            $buckets[$key] = Money::add($buckets[$key], $i->outstanding());
        }

        return array_map(fn ($v) => Money::amount($v), $buckets);
    }
}
