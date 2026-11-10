<?php

namespace App\Modules\Reports\Reports;

use App\Models\Contractor;
use App\Models\ContractorBill;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Dates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ContractorDuesReport extends BaseReport
{
    public function key(): string
    {
        return 'contractor-dues';
    }

    public function name(): string
    {
        return 'Contractor Dues';
    }

    public function group(): string
    {
        return 'contractor';
    }

    public function basis(): string
    {
        return 'Approved bills less approved payments; retention shown separately';
    }

    public function filters(): array
    {
        return ['project', 'contractor'];
    }

    public function search(): array
    {
        return ['bill_no'];
    }

    public function columns(): array
    {
        return ['Contractor' => 'text', 'Bill' => 'text', 'Date' => 'date', 'Project' => 'text', 'Gross' => 'money', 'Retention' => 'money', 'Payable' => 'money', 'Outstanding' => 'money', 'Days' => 'number'];
    }

    public function query(Request $request, User $user): Builder
    {
        $paid = '(SELECT COALESCE(SUM(p.amount),0) FROM contractor_payments p WHERE p.bill_id = contractor_bills.id AND p.status = ?)';
        $query = ContractorBill::query()->visibleTo($user)->whereIn('status', ['approved', 'paid'])->whereRaw("net_payable > {$paid}", ['approved'])
            ->with(['contractor:id,name', 'project:id,code'])->selectRaw("contractor_bills.*, net_payable - {$paid} AS outstanding_amount", ['approved']);
        if ($project = self::project($request, $user)) {
            $query->where('project_id', $project->id);
        }
        if ($c = Contractor::findByPublicId($request->input('contractor'))) {
            $query->where('contractor_id', $c->id);
        }

        return $query->orderBy('bill_date');
    }

    public function row(mixed $b): array
    {
        return [$b->contractor->name, $b->bill_no, $b->bill_date, $b->project->code, $b->gross_amount, $b->retention_amount, $b->net_payable, $b->outstanding_amount,
            max(0, (int) $b->bill_date->diffInDays(Dates::today(), false))];
    }
}
