<?php

namespace App\Support;

use App\Models\ContractorBill;
use App\Models\ContractorPayment;
use App\Models\ContractorWorkOrder;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\CustomerStatementEntry;
use App\Models\Expense;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorLedgerEntry;
use App\Models\InvestorPayout;
use App\Models\InvestorProfitDistribution;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Purchasing\Services\Unbilled;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Every dashboard figure, computed in one place from the same sources as the list pages, so a number on the
 * dashboard equals the tile of the list it links to. Operational figures only: profit, balances and cash flow
 * from the books arrive with the accounting phase and replace the "money in / out" panel.
 */
class Dashboard
{
    /** panel => permissions (any) */
    public const PANELS = [
        'attention' => [],
        'kpis' => ['collections.payment.view', 'sales.sale.view', 'purchases.view', 'contractors.view'],
        'projects' => ['projects.view', 'projects.view_all'],
        'today' => ['dashboard.executive.view'],
        'upcoming' => ['purchases.view', 'contractors.view', 'expenses.view.project', 'expenses.view.general', 'investor.view'],
        'debtors' => ['collections.plan.view'],
        'cashflow' => ['dashboard.executive.view'],
        'forecast' => ['collections.plan.view'],
        'aging' => ['collections.plan.view'],
        'units' => ['flats.view'],
        'milestones' => ['construction.view'],
        'investors' => ['investor.view'],
    ];

    /** Days late after which an overdue installment shows red instead of amber. */
    public const SOON_DAYS = 7;

    public const LATE_DAYS = 30;

    private CarbonImmutable $today;

    public function __construct(private User $user, private ?Project $project = null)
    {
        $this->today = Dates::today();
    }

    public function allowed(string $panel): bool
    {
        if (! array_key_exists($panel, self::PANELS)) {
            return false;
        }
        $permissions = self::PANELS[$panel];

        return $permissions === [] || $this->user->canAny($permissions);
    }

    /** @return array<int, string> */
    public function panels(): array
    {
        return array_values(array_filter(array_keys(self::PANELS), fn ($p) => $this->allowed($p)));
    }

    public function get(string $panel): array
    {
        $key = 'dashboard:'.$this->user->id.':'.$panel.':'.($this->project?->id ?? 'all').':'.$this->today->toDateString();

        return Cache::remember($key, 300, fn () => [...$this->{$panel}(), 'as_of' => now(config('app.display_timezone'))->format('d M Y, h:i A')]);
    }

    private function projectIds(): Builder
    {
        return Project::query()->visibleTo($this->user)->when($this->project, fn ($q) => $q->whereKey($this->project->id))->select('projects.id');
    }

    private function filters(array $extra = []): array
    {
        return array_filter([...$extra, 'project' => $this->project?->public_id]);
    }

    private function openItems(): Builder
    {
        return InstallmentScheduleItem::query()->where('status', '!=', 'rescheduled')->whereNotIn('status', ['paid'])
            ->whereIn('plan_id', InstallmentPlan::query()->where('status', 'active')->whereHas('sale', fn ($q) => $q->whereIn('project_id', $this->projectIds()))->select('id'))
            ->whereRaw('principal_due + fee_due - principal_paid - fee_paid > 0');
    }

    private const OUTSTANDING = 'principal_due + fee_due - principal_paid - fee_paid';

    // ---------------------------------------------------------------- panels

    private function attention(): array
    {
        $u = $this->user;
        $items = [];
        $overdueCustomers = collect();

        if ($u->can('collections.plan.view')) {
            $overdue = (clone $this->openItems())->whereDate('due_date', '<', $this->today)->with('plan:id,customer_id')->get(['id', 'plan_id', 'due_date', 'principal_due', 'fee_due', 'principal_paid', 'fee_paid']);
            $customers = Customer::query()->whereIn('id', $overdue->pluck('plan.customer_id')->unique())->get(['id', 'public_id', 'full_name', 'customer_code', 'primary_mobile', 'created_by'])->keyBy('id');
            $overdueCustomers = $overdue->groupBy('plan.customer_id')->map(fn ($rows, $cid) => [
                'name' => $customers[$cid]->full_name, 'mobile' => Phone::display($customers[$cid]->primary_mobile), 'href' => RecordLink::url($customers[$cid], $u),
                'amount' => Money::amount(Money::add('0', ...$rows->map(fn ($i) => $i->outstanding())->all())),
                'days' => (int) $rows->min('due_date')->diffInDays($this->today),
            ])->sortByDesc(fn ($r) => (float) $r['amount'])->take(8)->values();

            $week = (clone $this->openItems())->whereBetween('due_date', [$this->today->toDateString(), $this->today->addDays(6)->toDateString()]);
            $items[] = ['label' => 'Installments due this week', 'count' => (clone $week)->count(), 'amount' => (string) ((clone $week)->selectRaw('SUM('.self::OUTSTANDING.') AS a')->value('a') ?? 0),
                'href' => route('dues.index', $this->filters(['filter' => 'week'])), 'tone' => 'warning', 'icon' => 'calendar'];
        }
        if ($u->can('sales.booking.view')) {
            $n = FlatBooking::query()->whereIn('project_id', $this->projectIds())->where('status', 'confirmed')
                ->whereBetween('booking_expires_at', [$this->today->toDateString(), $this->today->addDays(7)->toDateString()])->count();
            $items[] = ['label' => 'Bookings expiring in 7 days', 'count' => $n, 'amount' => null, 'href' => route('bookings.index', $this->filters(['expiring' => '1'])), 'tone' => 'danger', 'icon' => 'clock'];
        }
        if ($u->can('purchases.view')) {
            $bills = SupplierBill::query()->visibleTo($u)->where('status', 'approved')->whereDate('due_date', '<', $this->today)
                ->when($this->project, fn ($q) => $q->whereHas('purchaseOrder', fn ($po) => $po->where('project_id', $this->project->id)))->get();
            $items[] = ['label' => 'Supplier bills past due date', 'count' => $bills->count(), 'amount' => Money::add('0', ...$bills->map(fn ($b) => $b->outstanding())->all()),
                'href' => route('supplier-bills.index', ['overdue' => '1']), 'tone' => 'danger', 'icon' => 'file'];
            $late = PurchaseOrder::query()->visibleTo($u)->whereIn('status', PurchaseOrder::RECEIVABLE)->whereDate('expected_date', '<', $this->today)
                ->when($this->project, fn ($q) => $q->where('project_id', $this->project->id))->count();
            $items[] = ['label' => 'Purchase orders past delivery date', 'count' => $late, 'amount' => null, 'href' => route('purchase-orders.index', $this->filters(['late' => '1'])), 'tone' => 'warning', 'icon' => 'note'];
            $unbilled = Unbilled::totals(Unbilled::late(Unbilled::lines($u))
                ->when($this->project, fn ($q) => $q->whereIn('goods_receipts.purchase_order_id', PurchaseOrder::query()->where('project_id', $this->project->id)->select('id'))));
            $items[] = ['label' => 'Goods not billed for '.Unbilled::ALERT_DAYS.'+ days', 'count' => $unbilled['receipts'], 'amount' => $unbilled['amount'],
                'href' => route('purchase-orders.index', $this->filters(['unbilled' => 'late'])), 'tone' => 'warning', 'icon' => 'download'];
        }
        if ($u->can('contractors.view')) {
            $old = ContractorBill::query()->visibleTo($u)->longUnpaid()->when($this->project, fn ($q) => $q->where('project_id', $this->project->id))->with('payments')->get();
            $items[] = ['label' => 'Contractor bills unpaid '.ContractorBill::OLD_DAYS.'+ days', 'count' => $old->count(), 'amount' => Money::add('0', ...$old->map(fn ($b) => $b->outstanding())->all()),
                'href' => route('contractor-bills.index', $this->filters(['old' => '1'])), 'tone' => 'danger', 'icon' => 'file'];
            $settle = ContractorWorkOrder::query()->visibleTo($u)->readyToSettle()->when($this->project, fn ($q) => $q->where('project_id', $this->project->id))->get();
            $items[] = ['label' => 'Work orders ready to settle', 'count' => $settle->count(), 'amount' => Money::add('0', ...$settle->map(fn ($w) => $w->retentionHeld())->all()),
                'href' => route('work-orders.index', $this->filters(['settle' => '1'])), 'tone' => 'warning', 'icon' => 'check-square'];
        }
        if ($u->can('materials.view')) {
            $warehouses = Warehouse::query()->visibleTo($u)->when($this->project, fn ($q) => $q->where('project_id', $this->project->id))->select('warehouses.id');
            $low = StockBalance::query()->whereIn('warehouse_id', $warehouses)
                ->whereHas('material', fn ($q) => $q->where('reorder_level', '>', 0)->whereColumn('materials.reorder_level', '>', 'stock_balances.owned_qty'))->count();
            $items[] = ['label' => 'Materials below reorder level', 'count' => $low, 'amount' => null, 'href' => route('stock.index', ['filter' => 'low']), 'tone' => 'warning', 'icon' => 'layers'];
        }

        return ['overdue' => $overdueCustomers->all(), 'items' => array_values(array_filter($items, fn ($i) => $i['count'] > 0)), 'late_days' => self::LATE_DAYS,
            'overdue_href' => route('dues.index', $this->filters(['filter' => 'overdue']))];
    }

    private function kpis(): array
    {
        $u = $this->user;
        $tiles = [];
        $month = [$this->today->startOfMonth()->toDateString(), $this->today->endOfMonth()->toDateString()];
        $last = [$this->today->subMonthNoOverflow()->startOfMonth()->toDateString(), $this->today->subMonthNoOverflow()->endOfMonth()->toDateString()];

        if ($u->can('collections.payment.view')) {
            $collected = (string) CustomerPayment::query()->whereIn('project_id', $this->projectIds())->where('status', 'posted')->whereBetween('payment_date', $month)->sum('amount');
            $due = $u->can('collections.plan.view')
                ? (string) (InstallmentScheduleItem::query()->where('status', '!=', 'rescheduled')->whereBetween('due_date', $month)
                    ->whereIn('plan_id', InstallmentPlan::query()->where('status', 'active')->whereHas('sale', fn ($q) => $q->whereIn('project_id', $this->projectIds()))->select('id'))
                    ->selectRaw('SUM(principal_due + fee_due) AS d')->value('d') ?? 0) : null;
            $tiles[] = ['label' => 'Collected This Month', 'value' => Money::amount($collected), 'tone' => 'success', 'icon' => 'check',
                'note' => $due !== null ? Format::money(Money::amount($due), false).' due this month'.(Money::isPositive($due) ? ' · '.Format::percent(round((float) $collected / (float) $due * 100, 1)) : '') : null,
                'href' => route('customer-payments.index', $this->filters(['status' => 'posted', 'from' => $month[0], 'to' => $month[1]]))];

            $sales = FlatSale::query()->whereIn('project_id', $this->projectIds())->whereIn('status', FlatSale::ACTIVE);
            $receivable = (string) (CustomerStatementEntry::query()->where(fn ($q) => $q->whereIn('sale_id', (clone $sales)->select('id'))
                ->orWhere(fn ($b) => $b->whereNull('sale_id')->whereIn('booking_id', (clone $sales)->whereNotNull('booking_id')->select('booking_id'))))
                ->selectRaw('SUM(charge_amount - credit_amount) AS b')->value('b') ?? 0);
            $overdue = $u->can('collections.plan.view') ? (string) ((clone $this->openItems())->whereDate('due_date', '<', $this->today)->selectRaw('SUM('.self::OUTSTANDING.') AS o')->value('o') ?? 0) : null;
            $tiles[] = ['label' => 'Receivable', 'value' => Money::amount($receivable), 'tone' => 'warning', 'icon' => 'tag',
                'note' => $overdue !== null ? Format::money(Money::amount($overdue), false).' overdue' : null, 'alert' => $overdue !== null && Money::isPositive($overdue),
                'href' => route('dues.index', $this->filters())];
        }
        if ($u->can('sales.sale.view')) {
            $sold = fn (array $range) => FlatSale::query()->whereIn('project_id', $this->projectIds())->whereIn('status', FlatSale::ACTIVE)->whereBetween('agreement_date', $range);
            $now = (string) $sold($month)->sum('net_contract_value');
            $before = (string) $sold($last)->sum('net_contract_value');
            $change = (float) $before > 0 ? round(((float) $now - (float) $before) / (float) $before * 100) : null;
            $tiles[] = ['label' => 'Sales This Month', 'value' => Money::amount($now), 'tone' => 'info', 'icon' => 'door',
                'note' => ListSummary::many($sold($month)->count(), 'unit').($change !== null ? ' · '.($change >= 0 ? '▲ ' : '▼ ').abs($change).'% vs last month' : ''),
                'href' => route('sales.index', $this->filters(['from' => $month[0], 'to' => $month[1]]))];
        }
        if ($u->canAny(['purchases.view', 'contractors.view'])) {
            $supplier = $u->can('purchases.view') ? SupplierBill::query()->visibleTo($u)->where('status', 'approved')
                ->when($this->project, fn ($q) => $q->whereHas('purchaseOrder', fn ($po) => $po->where('project_id', $this->project->id)))->get() : collect();
            $contractor = $u->can('contractors.view') ? ContractorBill::query()->visibleTo($u)->where('status', 'approved')
                ->when($this->project, fn ($q) => $q->where('project_id', $this->project->id))
                ->withSum(['payments as paid' => fn ($q) => $q->where('status', 'approved')], 'amount')->get() : collect();
            $owed = Money::add('0', ...$supplier->map(fn ($b) => $b->outstanding())->all(), ...$contractor->map(fn ($b) => Money::sub((string) $b->net_payable, (string) ($b->paid ?? 0)))->all());
            $pastDue = Money::add('0', ...$supplier->filter(fn ($b) => $b->due_date && $b->due_date->lt($this->today))->map(fn ($b) => $b->outstanding())->all());
            $tiles[] = ['label' => 'Payables', 'value' => Money::amount($owed), 'tone' => 'danger', 'icon' => 'briefcase',
                'note' => Format::money(Money::amount($pastDue), false).' past due date', 'alert' => Money::isPositive($pastDue),
                'href' => $u->can('purchases.view') ? route('supplier-bills.index', ['status' => 'approved']) : route('contractor-bills.index', ['status' => 'approved'])];
        }

        return ['tiles' => $tiles];
    }

    private function projects(): array
    {
        $u = $this->user;
        $projects = Project::query()->whereIn('id', $this->projectIds())->whereIn('status', ['planned', 'active', 'on_hold'])->with('stages:id,project_id,progress_percent')->orderBy('code')->get();
        $ids = $projects->pluck('id');
        $cost = $u->can('construction.cost.view') ? ProjectCostAllocation::query()->whereIn('project_id', $ids)->selectRaw('project_id, SUM(amount) AS a')->groupBy('project_id')->pluck('a', 'project_id') : null;
        $units = Flat::query()->whereIn('project_id', $ids)->selectRaw("project_id, COUNT(*) AS total, SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) AS sold")->groupBy('project_id')->get()->keyBy('project_id');
        $sold = $u->can('sales.sale.view') ? FlatSale::query()->whereIn('project_id', $ids)->whereIn('status', FlatSale::ACTIVE)->selectRaw('project_id, SUM(net_contract_value) AS v')->groupBy('project_id')->pluck('v', 'project_id') : null;
        $paid = $u->can('collections.payment.view') ? CustomerPayment::query()->whereIn('project_id', $ids)->where('status', 'posted')->selectRaw('project_id, SUM(amount) AS v')->groupBy('project_id')->pluck('v', 'project_id') : null;
        $pct = fn ($a, $b) => (float) $b > 0 ? round((float) $a / (float) $b * 100, 1) : null;
        // Units not sold yet, at list price: what the project can still bring in.
        $unsold = $sold !== null ? Flat::query()->whereIn('project_id', $ids)->where('status', '!=', 'sold')->selectRaw('project_id, SUM(list_price) AS v')->groupBy('project_id')->pluck('v', 'project_id') : null;

        return ['rows' => $projects->map(function (Project $p) use ($cost, $units, $sold, $paid, $pct, $unsold) {
            $progress = $p->overallProgress();
            $spent = $cost ? (string) ($cost[$p->id] ?? 0) : null;
            $used = $spent !== null ? $pct($spent, $p->estimated_budget) : null;

            return [
                'name' => $p->name, 'code' => $p->code, 'href' => RecordLink::url($p, $this->user), 'progress' => $progress, 'budget' => $p->estimated_budget, 'spent' => $spent, 'used' => $used,
                'flag' => $used !== null && $used > $progress + 10,
                'units' => (int) ($units[$p->id]->total ?? 0), 'units_sold' => (int) ($units[$p->id]->sold ?? 0),
                'sold_value' => $sold ? (string) ($sold[$p->id] ?? 0) : null,
                'collected' => $paid ? (string) ($paid[$p->id] ?? 0) : null,
                'collected_pct' => $sold && $paid ? $pct($paid[$p->id] ?? 0, $sold[$p->id] ?? 0) : null,
                // Sold value plus unsold units at list price, less the budget: the profit the project is heading for.
                'outlook' => $cost !== null && $unsold !== null && (float) $p->estimated_budget > 0 ? [
                    'revenue' => $revenue = Money::add((string) ($sold[$p->id] ?? 0), (string) ($unsold[$p->id] ?? 0)),
                    'profit' => Money::amount(Money::sub($revenue, (string) $p->estimated_budget)),
                    'margin' => $pct(Money::sub($revenue, (string) $p->estimated_budget), $revenue),
                ] : null,
            ];
        })->all(), 'show_cost' => $cost !== null, 'show_sales' => $sold !== null, 'show_paid' => $paid !== null, 'show_outlook' => $cost !== null && $unsold !== null];
    }

    /** Money received and paid out today, company wide (operational, not the books). */
    private function today(): array
    {
        $day = $this->today->toDateString();
        $range = ['from' => $day, 'to' => $day];
        $line = function (string $label, Builder $query, string $date, string $href, string $amount = 'amount') use ($day): array {
            $row = $query->whereDate($date, $day)->selectRaw("COUNT(*) AS n, COALESCE(SUM({$amount}), 0) AS v")->toBase()->first();

            return ['label' => $label, 'count' => (int) $row->n, 'amount' => Money::amount((string) $row->v), 'href' => $href];
        };
        $in = [
            $line('Customer payments', CustomerPayment::query()->where('status', 'posted'), 'payment_date', route('customer-payments.index', ['status' => 'posted'] + $range)),
            $line('Investor capital', InvestorCapitalTransaction::query()->where('status', 'posted')->where('transaction_type', 'contribution'), 'transaction_date', route('investor-capital.index', $range)),
        ];
        $out = [
            $line('Supplier payments', SupplierPayment::query()->where('status', 'approved'), 'payment_date', route('supplier-payments.index', $range)),
            $line('Contractor payments', ContractorPayment::query()->where('status', 'approved'), 'payment_date', route('contractor-payments.index', $range)),
            $line('Expenses', Expense::query()->where('status', 'paid'), 'paid_on', route('expenses.index', ['status' => 'paid'])),
            $line('Investor profit', InvestorPayout::query()->where('status', 'posted'), 'paid_at', route('distributions.index')),
            $line('Capital returned', InvestorCapitalTransaction::query()->where('status', 'posted')->where('transaction_type', 'capital_return'), 'transaction_date', route('investor-capital.index', $range)),
            $line('Customer refunds', CustomerRefund::query()->where('status', 'paid'), 'paid_on', route('refunds.index'), 'COALESCE(net_amount, amount)'),
        ];
        $sum = fn (array $lines) => Money::amount(Money::add('0', ...array_column($lines, 'amount')));

        return ['date' => $this->today, 'in' => array_values(array_filter($in, fn ($l) => $l['count'])), 'out' => array_values(array_filter($out, fn ($l) => $l['count'])),
            'total_in' => $sum($in), 'total_out' => $sum($out), 'net' => Money::amount(Money::sub($sum($in), $sum($out)))];
    }

    /** What has to be paid now or within a week: supplier bills, contractor bills, approved expenses and approved profit payments. */
    private function upcoming(): array
    {
        $u = $this->user;
        $until = $this->today->addDays(self::SOON_DAYS);
        $rows = collect();
        if ($u->can('purchases.view')) {
            SupplierBill::query()->visibleTo($u)->where('status', 'approved')->where(fn ($q) => $q->whereNull('due_date')->orWhereDate('due_date', '<=', $until))
                ->when($this->project, fn ($q) => $q->whereHas('purchaseOrder', fn ($po) => $po->where('project_id', $this->project->id)))
                ->with('supplier:id,name')->get()
                ->each(fn (SupplierBill $b) => $rows->push(['who' => $b->supplier?->name, 'what' => 'Supplier bill '.$b->bill_no, 'href' => RecordLink::url($b, $u), 'due' => $b->due_date, 'amount' => $b->outstanding()]));
        }
        if ($u->can('contractors.view')) {
            ContractorBill::query()->visibleTo($u)->where('status', 'approved')->when($this->project, fn ($q) => $q->where('project_id', $this->project->id))
                ->with('contractor:id,name')->withSum(['payments as paid' => fn ($q) => $q->where('status', 'approved')], 'amount')->get()
                ->each(fn (ContractorBill $b) => $rows->push(['who' => $b->contractor?->name, 'what' => 'Contractor bill '.$b->bill_no, 'href' => RecordLink::url($b, $u), 'due' => null,
                    'amount' => Money::amount(Money::sub((string) $b->net_payable, (string) ($b->paid ?? 0)))]));
        }
        if ($u->canAny(['expenses.view.project', 'expenses.view.general'])) {
            Expense::query()->visibleTo($u)->where('status', 'approved')->when($this->project, fn ($q) => $q->where('project_id', $this->project->id))->get()
                ->each(fn (Expense $e) => $rows->push(['who' => $e->payee_name ?: $e->description, 'what' => 'Expense '.$e->expense_no, 'href' => RecordLink::url($e, $u), 'due' => null, 'amount' => Money::amount((string) $e->amount)]));
        }
        if ($u->can('investor.view')) {
            InvestorProfitDistribution::query()->visibleTo($u)->where('status', 'approved')->whereIn('flat_id', Flat::query()->whereIn('project_id', $this->projectIds())->select('flats.id'))
                ->with('investor:id,name')->get()
                ->each(fn (InvestorProfitDistribution $d) => $rows->push(['who' => $d->investor?->name, 'what' => 'Investor profit '.$d->distribution_no, 'href' => RecordLink::url($d, $u), 'due' => null, 'amount' => Money::amount((string) $d->amount)]));
        }
        $rows = $rows->filter(fn ($r) => Money::isPositive($r['amount']))
            ->map(fn ($r) => [...$r, 'days' => $r['due'] ? Dates::daysUntil($r['due']) : null, 'due' => $r['due'] ? Format::date($r['due']) : null])
            // Overdue first, then by due date; documents with no due date are payable now and follow the dated ones.
            ->sortBy(fn ($r) => $r['days'] ?? PHP_INT_MAX)->values();

        return ['rows' => $rows->take(8)->all(), 'more' => max(0, $rows->count() - 8), 'count' => $rows->count(),
            'total' => Money::amount(Money::add('0', ...$rows->pluck('amount')->all())), 'days' => self::SOON_DAYS];
    }

    /** Customers with the most overdue money, with how late the oldest installment is. */
    private function debtors(): array
    {
        $items = (clone $this->openItems())->whereDate('due_date', '<', $this->today)->get(['plan_id', 'due_date', 'principal_due', 'fee_due', 'principal_paid', 'fee_paid']);
        $plans = InstallmentPlan::query()->whereIn('id', $items->pluck('plan_id')->unique())->with('customer:id,public_id,full_name,primary_mobile')->get(['id', 'customer_id'])->keyBy('id');
        $rows = $items->groupBy(fn ($i) => $plans[$i->plan_id]?->customer_id)->filter(fn ($group, $customerId) => $customerId)->map(function (Collection $group) use ($plans) {
            $customer = $plans[$group->first()->plan_id]->customer;

            return ['name' => $customer->full_name, 'mobile' => $customer->primary_mobile, 'href' => RecordLink::url($customer, $this->user),
                'amount' => Money::amount(Money::add('0', ...$group->map(fn ($i) => $i->outstanding())->all())),
                'installments' => $group->count(), 'days' => (int) $group->sortBy('due_date')->first()->due_date->diffInDays($this->today)];
        })->sortByDesc(fn ($r) => (float) $r['amount'])->values();

        return ['rows' => $rows->take(5)->all(), 'more' => max(0, $rows->count() - 5), 'href' => route('dues.index', $this->filters(['filter' => 'overdue']))];
    }

    /** Money received and paid out per month, last six months, company wide (operational, not the books). */
    private function cashflow(): array
    {
        $start = $this->today->startOfMonth()->subMonths(5);
        $months = collect(range(0, 5))->mapWithKeys(fn ($i) => [$start->addMonths($i)->format('Y-m') => ['in' => '0', 'out' => '0']]);
        $add = function (string $side, Collection $rows) use (&$months) {
            foreach ($rows as $m => $v) {
                if ($months->has($m)) {
                    $months[$m] = [...$months[$m], $side => Money::add($months[$m][$side], (string) $v)];
                }
            }
        };
        $by = fn (Builder $q, string $date, string $amount = 'amount') => $q->where($date, '>=', $start->toDateString())
            ->selectRaw("DATE_FORMAT({$date}, '%Y-%m') AS m, SUM({$amount}) AS v")->groupByRaw("DATE_FORMAT({$date}, '%Y-%m')")->pluck('v', 'm');

        $add('in', $by(CustomerPayment::query()->where('status', 'posted'), 'payment_date'));
        $add('in', $by(InvestorCapitalTransaction::query()->where('status', 'posted')->where('transaction_type', 'contribution'), 'transaction_date'));
        $add('out', $by(SupplierPayment::query()->where('status', 'approved'), 'payment_date'));
        $add('out', $by(ContractorPayment::query()->where('status', 'approved'), 'payment_date'));
        $add('out', $by(Expense::query()->where('status', 'paid'), 'paid_on'));
        $add('out', $by(InvestorPayout::query()->where('status', 'posted'), 'paid_at'));
        $add('out', $by(InvestorCapitalTransaction::query()->where('status', 'posted')->where('transaction_type', 'capital_return'), 'transaction_date'));
        $add('out', $by(CustomerRefund::query()->where('status', 'paid'), 'paid_on', 'COALESCE(net_amount, amount)'));

        $rows = $months->map(fn ($v, $m) => ['month' => CarbonImmutable::createFromFormat('Y-m-d', $m.'-01')->format('M Y'), 'in' => Money::amount($v['in']), 'out' => Money::amount($v['out']),
            'net' => Money::amount(Money::sub($v['in'], $v['out']))])->values();

        return ['rows' => $rows->all(), 'max' => max(1, (float) $rows->max(fn ($r) => max((float) $r['in'], (float) $r['out'])))];
    }

    /** Open installments by due month: overdue now, then the next three months. */
    private function forecast(): array
    {
        $rows = (clone $this->openItems())->whereDate('due_date', '<=', $this->today->addMonths(2)->endOfMonth())
            ->get(['due_date', 'principal_due', 'fee_due', 'principal_paid', 'fee_paid']);
        $buckets = ['Overdue now' => '0'];
        foreach (range(0, 2) as $i) {
            $buckets[$this->today->addMonths($i)->format('M Y')] = '0';
        }
        foreach ($rows as $item) {
            $key = $item->due_date->lt($this->today) ? 'Overdue now' : $item->due_date->format('M Y');
            if (isset($buckets[$key])) {
                $buckets[$key] = Money::add($buckets[$key], $item->outstanding());
            }
        }

        return ['buckets' => array_map(fn ($v) => Money::amount($v), $buckets), 'href' => route('dues.index', $this->filters())];
    }

    private function aging(): array
    {
        $buckets = ['1–30 days' => '0', '31–60 days' => '0', '61–90 days' => '0', 'Over 90 days' => '0'];
        foreach ((clone $this->openItems())->whereDate('due_date', '<', $this->today)->get(['due_date', 'principal_due', 'fee_due', 'principal_paid', 'fee_paid']) as $item) {
            $days = (int) $item->due_date->diffInDays($this->today, false);
            $key = match (true) {
                $days <= 30 => '1–30 days', $days <= 60 => '31–60 days', $days <= 90 => '61–90 days', default => 'Over 90 days'
            };
            $buckets[$key] = Money::add($buckets[$key], $item->outstanding());
        }

        return ['buckets' => array_map(fn ($v) => Money::amount($v), $buckets), 'href' => route('dues.index', $this->filters(['filter' => 'overdue']))];
    }

    private function units(): array
    {
        $rows = Flat::query()->whereIn('project_id', $this->projectIds())
            ->selectRaw("project_id, SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available, SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) AS booked,
                SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) AS sold, SUM(CASE WHEN status = 'not_released' THEN 1 ELSE 0 END) AS not_released, COUNT(*) AS total, SUM(CASE WHEN status = 'available' THEN list_price ELSE 0 END) AS available_value")
            ->groupBy('project_id')->get();
        $projects = Project::query()->whereIn('id', $rows->pluck('project_id'))->get(['id', 'public_id', 'code', 'name'])->keyBy('id');

        return ['rows' => $rows->map(fn ($r) => ['code' => $projects[$r->project_id]->code, 'href' => RecordLink::url($projects[$r->project_id], $this->user),
            'available_href' => route('flats.index', ['project' => $projects[$r->project_id]->public_id, 'status' => 'available']), 'available' => (int) $r->available, 'booked' => (int) $r->booked, 'sold' => (int) $r->sold, 'not_released' => (int) $r->not_released,
            'total' => (int) $r->total, 'available_value' => Money::amount((string) $r->available_value)])->sortBy('code')->values()->all()];
    }

    private function milestones(): array
    {
        $pending = fn () => Milestone::query()->whereIn('project_id', $this->projectIds())->where('status', 'pending');
        $row = fn (Milestone $m, int $days) => ['code' => $m->project->code, 'href' => RecordLink::url($m->project, $this->user), 'title' => $m->title, 'days' => $days];
        $late = $pending()->whereDate('planned_at', '<', $this->today)->with('project:id,public_id,code,name')->orderBy('planned_at')->limit(8)->get();
        $soon = $pending()->whereBetween('planned_at', [$this->today->toDateString(), $this->today->addDays(self::SOON_DAYS)->toDateString()])
            ->with('project:id,public_id,code,name')->orderBy('planned_at')->limit(5)->get();

        return ['rows' => $late->map(fn ($m) => $row($m, (int) $m->planned_at->diffInDays($this->today)))->all(),
            'total' => $pending()->whereDate('planned_at', '<', $this->today)->count(),
            'soon' => $soon->map(fn ($m) => $row($m, (int) $this->today->diffInDays($m->planned_at)))->all(),
            'soon_days' => self::SOON_DAYS];
    }

    private function investors(): array
    {
        $flats = Flat::query()->visibleTo($this->user)->whereIn('project_id', $this->projectIds())->select('flats.id');
        $kind = 'COALESCE(o.entry_kind, e.entry_kind)';
        $row = InvestorLedgerEntry::query()->from('investor_ledger_entries as e')->leftJoin('investor_ledger_entries as o', 'o.id', '=', 'e.reversal_of_id')
            ->whereIn('e.flat_id', $flats)
            ->selectRaw("SUM(CASE WHEN {$kind} = 'capital_in' THEN e.amount WHEN {$kind} = 'capital_out' THEN -e.amount ELSE 0 END) AS capital")
            ->selectRaw("SUM(CASE WHEN {$kind} = 'profit_payable' THEN e.amount ELSE 0 END) AS payable")
            ->selectRaw("SUM(CASE WHEN {$kind} = 'profit_paid' THEN e.amount ELSE 0 END) AS paid")->toBase()->first();
        $approved = InvestorProfitDistribution::query()->whereIn('flat_id', $flats)->whereIn('status', ['approved', 'partially_paid'])
            ->withSum(['payouts as paid' => fn ($q) => $q->where('status', 'posted')], 'amount')->get()
            ->sum(fn ($d) => (float) $d->amount - (float) ($d->paid ?? 0));

        return [
            'capital' => Money::amount((string) ($row->capital ?? 0)),
            'due' => Money::amount(Money::sub((string) ($row->payable ?? 0), (string) ($row->paid ?? 0))),
            'paid' => Money::amount((string) ($row->paid ?? 0)),
            'approved_unpaid' => Money::amount((string) $approved),
        ];
    }
}
