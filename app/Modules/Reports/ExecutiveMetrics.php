<?php

namespace App\Modules\Reports;

use App\Models\ContractorBill;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\CustomerStatementEntry;
use App\Models\Expense;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\FlatCostAllocationRun;
use App\Models\FlatPriceRevision;
use App\Models\FlatProfitCalculation;
use App\Models\FlatSale;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorLedgerEntry;
use App\Models\InvestorPayout;
use App\Models\InvestorProfitAllocation;
use App\Models\LateFeeAssessment;
use App\Models\MaterialBorrow;
use App\Models\MaterialTransfer;
use App\Models\Milestone;
use App\Models\PaymentAllocation;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Models\SalesCancellation;
use App\Models\StockBalance;
use App\Models\SupplierBill;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Reports\Reports\AgingBuckets;
use App\Support\ApprovalQueue;
use App\Support\Money;
use App\Support\Reconciliation\SubledgerReconciler;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Executive dashboard figures, each from the ledger or document that owns it and scoped to the user's
 * projects. Every widget keeps its basis apart: billed vs collected, payable vs paid, owned vs borrowed.
 */
class ExecutiveMetrics
{
    /** widget => permissions (any) */
    public const WIDGETS = [
        'projects' => ['projects.view', 'projects.view_all'],
        'units' => ['flats.view'],
        'sales' => ['sales.sale.view', 'sales.booking.view'],
        'collections' => ['collections.payment.view'],
        'construction' => ['construction.view', 'reports.construction'],
        'inventory' => ['materials.view'],
        'investors' => ['investor.view'],
        'payables' => ['purchases.view', 'contractors.view'],
        'exceptions' => ['dashboard.executive.view'],
    ];

    private ?Project $project;

    private Carbon $from;

    private Carbon $to;

    public function __construct(private User $user, ?string $project, ?string $from, ?string $to)
    {
        $this->project = $project ? Project::query()->visibleTo($user)->where('public_id', $project)->first() : null;
        $today = now(config('app.display_timezone'));
        $parse = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? Carbon::parse($v) : null;
        $this->from = $parse($from) ?? $today->copy()->startOfMonth();
        $this->to = $parse($to) ?? $today->copy()->endOfMonth();
    }

    public function allowed(string $widget): bool
    {
        return isset(self::WIDGETS[$widget]) && $this->user->canAny(self::WIDGETS[$widget]);
    }

    /** Cached per user and filters for five minutes. */
    public function get(string $widget): array
    {
        $key = 'exec:'.$this->user->id.':'.$widget.':'.md5(($this->project?->id ?? '-').$this->from->toDateString().$this->to->toDateString());

        return Cache::remember($key, 300, fn () => [...$this->{$widget}(), 'as_of' => now(config('app.display_timezone'))->format('d M Y, h:i A')]);
    }

    public function period(): array
    {
        return [$this->from, $this->to];
    }

    private function projectIds(): Builder
    {
        return $this->project ? Project::query()->whereKey($this->project->id)->select('id') : Project::query()->visibleTo($this->user)->select('projects.id');
    }

    private function between(Builder $q, string $column): Builder
    {
        return $q->whereDate($column, '>=', $this->from->toDateString())->whereDate($column, '<=', $this->to->toDateString());
    }

    private static function sum($value): string
    {
        return Money::amount((string) ($value ?? 0));
    }

    private function projects(): array
    {
        $projects = Project::query()->whereIn('id', $this->projectIds())->with('stages')
            ->addSelect(['actual' => ProjectCostAllocation::query()->whereColumn('project_id', 'projects.id')->selectRaw('COALESCE(SUM(amount),0)')])->get();

        return [
            'by_status' => $projects->groupBy('status')->map->count()->mapWithKeys(fn ($n, $s) => [Project::STATUSES[$s] ?? $s => $n])->all(),
            'budget' => $projects->sortByDesc('actual')->take(6)->map(fn ($p) => ['name' => $p->code.' · '.$p->name, 'budget' => self::sum($p->estimated_budget), 'actual' => self::sum($p->actual), 'progress' => $p->overallProgress()])->values()->all(),
            'overdue_milestones' => Milestone::query()->whereIn('project_id', $this->projectIds())->where('status', 'pending')->whereDate('planned_at', '<', now()->toDateString())->count(),
        ];
    }

    private function units(): array
    {
        $flats = Flat::query()->whereIn('project_id', $this->projectIds());
        $counts = (clone $flats)->selectRaw('status, COUNT(*) AS n, SUM(list_price) AS v')->groupBy('status')->get()->keyBy('status');
        $sales = FlatSale::query()->whereIn('project_id', $this->projectIds())->whereIn('status', FlatSale::ACTIVE);

        return [
            'counts' => collect(Flat::STATUSES)->mapWithKeys(fn ($label, $s) => [$label => (int) ($counts[$s]->n ?? 0)])->all(),
            'registered' => (clone $sales)->whereHas('registration', fn ($q) => $q->where('status', 'registered'))->count(),
            'handed_over' => (clone $sales)->whereHas('handover', fn ($q) => $q->where('status', 'completed'))->count(),
            'available_value' => self::sum($counts['available']->v ?? 0),
            'sold_value' => self::sum((clone $sales)->sum('net_contract_value')),
        ];
    }

    private function sales(): array
    {
        $ids = $this->projectIds();

        return [
            'bookings' => $this->between(FlatBooking::query()->whereIn('project_id', $ids)->whereNotIn('status', ['draft', 'pending', 'rejected']), 'booking_date')->count(),
            'sales' => $this->between(FlatSale::query()->whereIn('project_id', $ids)->whereIn('status', FlatSale::ACTIVE), 'agreement_date')->count(),
            'net_sales' => self::sum($this->between(FlatSale::query()->whereIn('project_id', $ids)->whereIn('status', FlatSale::ACTIVE), 'agreement_date')->sum('net_contract_value')),
            'cancellations' => $this->between(SalesCancellation::query()->where('status', 'approved')
                ->where(fn ($q) => $q->whereIn('sale_id', FlatSale::query()->whereIn('project_id', $ids)->select('id'))->orWhereIn('booking_id', FlatBooking::query()->whereIn('project_id', $ids)->select('id'))), 'approved_at')->count(),
            'refunds' => self::sum($this->between(CustomerRefund::query()->whereIn('project_id', $ids)->where('status', 'paid'), 'paid_on')->get()->sum(fn ($r) => (float) $r->payable())),
            'price_changes' => $this->between(FlatPriceRevision::query()->where('status', 'approved')->whereIn('flat_id', Flat::query()->whereIn('project_id', $ids)->select('id')), 'updated_at')->count(),
        ];
    }

    private function collections(): array
    {
        $ids = $this->projectIds();
        $today = now(config('app.display_timezone'))->toDateString();
        $items = InstallmentScheduleItem::query()->whereIn('plan_id', InstallmentPlan::query()->where('status', 'active')->whereHas('sale', fn ($q) => $q->whereIn('project_id', $ids))->select('id'))
            ->where('status', '!=', 'rescheduled');
        $open = 'COALESCE(SUM(principal_due + fee_due - principal_paid - fee_paid),0)';
        $sales = FlatSale::query()->whereIn('project_id', $ids)->whereIn('status', FlatSale::ACTIVE);

        return [
            'collected' => self::sum($this->between(CustomerPayment::query()->whereIn('project_id', $ids)->where('status', 'posted'), 'payment_date')->sum('amount')),
            'receivable' => self::sum(CustomerStatementEntry::query()->where(fn ($q) => $q->whereIn('sale_id', (clone $sales)->select('id'))
                ->orWhere(fn ($b) => $b->whereNull('sale_id')->whereIn('booking_id', (clone $sales)->whereNotNull('booking_id')->select('booking_id'))))->selectRaw('SUM(charge_amount - credit_amount) AS b')->value('b')),
            'overdue' => self::sum((clone $items)->whereDate('due_date', '<', $today)->selectRaw("{$open} AS o")->value('o')),
            'next_30' => self::sum((clone $items)->whereDate('due_date', '>=', $today)->whereDate('due_date', '<=', now()->addDays(30)->toDateString())->selectRaw("{$open} AS o")->value('o')),
            'advances' => self::sum(PaymentAllocation::query()->where('allocation_type', 'unapplied_advance')->whereIn('payment_id', CustomerPayment::query()->whereIn('project_id', $ids)->where('status', 'posted')->select('id'))->sum('amount')),
            'fees_billed' => self::sum($this->between(LateFeeAssessment::query()->where('status', 'posted')->whereIn('schedule_item_id', (clone $items)->select('id')), 'created_at')->sum('amount')),
            'fees_collected' => self::sum(PaymentAllocation::query()->where('allocation_type', 'late_fee')->whereIn('payment_id', $this->between(CustomerPayment::query()->whereIn('project_id', $ids)->where('status', 'posted'), 'payment_date')->select('id'))->sum('amount')),
        ];
    }

    private function construction(): array
    {
        $ids = $this->projectIds();
        $cost = $this->between(ProjectCostAllocation::query()->whereIn('project_id', $ids), 'effective_at')->selectRaw('cost_category, SUM(amount) AS t')->groupBy('cost_category')->pluck('t', 'cost_category');
        $general = $this->between(Expense::query()->where('scope', 'general')->whereIn('status', ['approved', 'paid']), 'expense_date');

        return [
            'by_category' => collect(ProjectCostAllocation::CATEGORIES)->mapWithKeys(fn ($label, $c) => [$label => self::sum($cost[$c] ?? 0)])->all(),
            'project_total' => self::sum($cost->sum()),
            'general_expenses' => $this->user->can('expenses.view.general') ? self::sum($general->sum('amount')) : null,
            'shared_pool' => self::sum(FlatCostAllocationRun::query()->whereIn('project_id', $ids)->where('status', 'approved')->sum('eligible_shared_cost')),
        ];
    }

    private function inventory(): array
    {
        $warehouses = Warehouse::query()->visibleTo($this->user)->when($this->project, fn ($q) => $q->where('project_id', $this->project->id))->select('warehouses.id');
        $balances = StockBalance::query()->whereIn('warehouse_id', $warehouses);

        return [
            'owned_value' => self::sum((clone $balances)->sum('owned_value')),
            'borrowed_in' => (clone $balances)->where('borrowed_in_qty', '>', 0)->count(),
            'lent_out' => (clone $balances)->where('borrowed_out_qty', '>', 0)->count(),
            'open_borrows' => MaterialBorrow::query()->whereIn('status', MaterialBorrow::OPEN)->where(fn ($q) => $q->whereIn('source_warehouse_id', $warehouses)->orWhereIn('destination_warehouse_id', $warehouses))->count(),
            'low_stock' => (clone $balances)->whereHas('material', fn ($q) => $q->where('reorder_level', '>', 0)->whereColumn('materials.reorder_level', '>', 'stock_balances.owned_qty'))->count(),
            'transfers' => $this->between(MaterialTransfer::query()->where('status', 'posted')->where(fn ($q) => $q->whereIn('source_warehouse_id', $warehouses)->orWhereIn('destination_warehouse_id', $warehouses)), 'updated_at')->count(),
        ];
    }

    private function investors(): array
    {
        $flats = Flat::query()->whereIn('project_id', $this->projectIds())->select('id');
        $kind = 'COALESCE(o.entry_kind, e.entry_kind)';
        $due = InvestorLedgerEntry::query()->from('investor_ledger_entries as e')->leftJoin('investor_ledger_entries as o', 'o.id', '=', 'e.reversal_of_id')->whereIn('e.flat_id', $flats)
            ->selectRaw("COALESCE(SUM(CASE WHEN {$kind} = 'profit_payable' THEN e.amount WHEN {$kind} = 'profit_paid' THEN -e.amount ELSE 0 END),0) AS d")->value('d');
        $pending = FlatProfitCalculation::query()->whereIn('flat_id', $flats)->where('status', 'submitted');

        return [
            'capital_received' => self::sum($this->between(InvestorCapitalTransaction::query()->whereIn('flat_id', $flats)->where('status', 'posted')->where('transaction_type', 'contribution'), 'transaction_date')->sum('amount')),
            'pending_count' => (clone $pending)->count(),
            'pending_amount' => self::sum(InvestorProfitAllocation::query()->whereIn('profit_calculation_id', (clone $pending)->select('id'))->sum('profit_amount')),
            'approved_due' => self::sum($due),
            'paid' => self::sum($this->between(InvestorPayout::query()->where('status', 'posted')->whereHas('distribution', fn ($q) => $q->whereIn('flat_id', $flats)), 'paid_at')->sum('amount')),
        ];
    }

    private function payables(): array
    {
        $result = [];
        if ($this->user->can('purchases.view')) {
            $bills = SupplierBill::query()->visibleTo($this->user)->whereIn('status', ['approved', 'paid'])->get(['id', 'status', 'total_amount', 'due_date', 'invoice_date']);
            $buckets = [];
            $today = now(config('app.display_timezone'))->startOfDay();
            foreach ($bills as $b) {
                $out = $b->outstanding();
                if (Money::isPositive($out)) {
                    $label = AgingBuckets::label(max(0, (int) ($b->due_date ?? $b->invoice_date)->diffInDays($today, false)));
                    $buckets[$label] = Money::add($buckets[$label] ?? '0', $out);
                }
            }
            $result['supplier_total'] = self::sum(Money::add('0', ...array_values($buckets)));
            $result['supplier_aging'] = collect(['Not due', '1–30 days', '31–60 days', '61–90 days', 'Over 90 days'])->mapWithKeys(fn ($l) => [$l => self::sum($buckets[$l] ?? 0)])->all();
        }
        if ($this->user->can('contractors.view')) {
            $result['contractor_total'] = self::sum(ContractorBill::query()->visibleTo($this->user)->whereIn('status', ['approved', 'paid'])->get()->sum(fn ($b) => (float) $b->outstanding()));
        }

        return $result;
    }

    private function exceptions(): array
    {
        return [
            'queue' => app(ApprovalQueue::class)->for($this->user),
            'overdue_milestones' => Milestone::query()->whereIn('project_id', $this->projectIds())->where('status', 'pending')->whereDate('planned_at', '<', now()->toDateString())
                ->with('project:id,code')->orderBy('planned_at')->limit(5)->get()->map(fn ($m) => ['title' => $m->title, 'project' => $m->project->code, 'planned' => $m->planned_at->format('d M Y')])->all(),
            'reconciliation' => $this->user->can('financial.events.reconcile')
                ? Cache::remember('exec:reconcile', 600, fn () => count(app(SubledgerReconciler::class)->differences()))
                : null,
        ];
    }
}
