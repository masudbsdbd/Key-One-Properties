<?php

namespace App\Modules\Reports\Reports;

use App\Models\Contractor;
use App\Models\ContractorWorkOrder;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Where every work order stands: contract, work measured, billed, paid, what is due now and what is held back. */
class WorkOrderStatusReport extends BaseReport
{
    private const SUMS = [
        'measured_amount' => "(SELECT COALESCE(SUM(m.measured_amount), 0) FROM contractor_measurements m WHERE m.work_order_id = contractor_work_orders.id AND m.status IN ('approved', 'billed'))",
        'billed_amount' => "(SELECT COALESCE(SUM(b.gross_amount), 0) FROM contractor_bills b WHERE b.work_order_id = contractor_work_orders.id AND b.kind = 'running' AND b.status IN ('approved', 'paid'))",
        'paid_amount' => "(SELECT COALESCE(SUM(CASE WHEN p.kind = 'refund' THEN -p.amount ELSE p.amount END), 0) FROM contractor_payments p WHERE p.work_order_id = contractor_work_orders.id AND p.status = 'approved')",
        'due_amount' => "(SELECT COALESCE(SUM(b.net_payable), 0) FROM contractor_bills b WHERE b.work_order_id = contractor_work_orders.id AND b.status = 'approved')
            - (SELECT COALESCE(SUM(p.amount), 0) FROM contractor_payments p JOIN contractor_bills b ON b.id = p.bill_id WHERE b.work_order_id = contractor_work_orders.id AND b.status = 'approved' AND p.status = 'approved')",
    ];

    public function key(): string
    {
        return 'work-order-status';
    }

    public function name(): string
    {
        return 'Work Order Status';
    }

    public function group(): string
    {
        return 'contractor';
    }

    public function basis(): string
    {
        return 'Approved measurements, bills and payments; retention is held until the work order is settled';
    }

    public function filters(): array
    {
        return ['project', 'status', 'contractor'];
    }

    public function statuses(): array
    {
        return ContractorWorkOrder::STATUSES;
    }

    public function search(): array
    {
        return ['contractor_work_orders.wo_no', 'contractor_work_orders.title'];
    }

    public function columns(): array
    {
        return ['Contractor' => 'text', 'Work Order' => 'text', 'Status' => 'text', 'Contract' => 'money', 'Work Done' => 'money', 'Billed' => 'money', 'Paid' => 'money',
            'Due Now' => 'money', 'Retention' => 'money'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = ContractorWorkOrder::query()->visibleTo($user)->with(['contractor:id,name', 'project:id,code'])->select('contractor_work_orders.*');
        foreach (self::SUMS as $alias => $sql) {
            $query->selectRaw($sql.' AS '.$alias);
        }
        if ($project = self::project($request, $user)) {
            $query->where('contractor_work_orders.project_id', $project->id);
        }
        if (array_key_exists($status = (string) $request->input('status'), ContractorWorkOrder::STATUSES)) {
            $query->where('contractor_work_orders.status', $status);
        }
        if ($contractor = Contractor::findByPublicId($request->input('contractor'))) {
            $query->where('contractor_work_orders.contractor_id', $contractor->id);
        }

        return $query->orderByDesc('contractor_work_orders.id');
    }

    public function row(mixed $w): array
    {
        return [$w->contractor->name, $w->wo_no.' · '.$w->project->code, ContractorWorkOrder::STATUSES[$w->status] ?? $w->status, $w->contract_amount, Money::amount((string) $w->measured_amount),
            Money::amount((string) $w->billed_amount), Money::amount((string) $w->paid_amount), Money::amount((string) $w->due_amount), $w->retentionHeld()];
    }

    public function totals(Builder $query): ?array
    {
        $rows = (clone $query)->reorder()->get();
        $sum = fn (callable $value) => Money::amount(Money::add('0', ...$rows->map(fn ($r) => (string) $value($r))->all()));

        return ['Total', null, null, $sum(fn ($r) => $r->contract_amount), $sum(fn ($r) => $r->measured_amount), $sum(fn ($r) => $r->billed_amount), $sum(fn ($r) => $r->paid_amount),
            $sum(fn ($r) => $r->due_amount), $sum(fn ($r) => $r->retentionHeld())];
    }
}
