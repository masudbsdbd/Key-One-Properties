<?php

namespace App\Modules\Reports\Reports;

use App\Models\FlatSale;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SalesRegisterReport extends BaseReport
{
    public function key(): string
    {
        return 'sales-register';
    }

    public function name(): string
    {
        return 'Sales Register';
    }

    public function group(): string
    {
        return 'sales';
    }

    public function basis(): string
    {
        return 'Approved sales by agreement date (cancelled shown with status)';
    }

    public function filters(): array
    {
        return ['project', 'period', 'status'];
    }

    public function statuses(): array
    {
        return array_intersect_key(FlatSale::STATUSES, array_flip([...FlatSale::ACTIVE, 'cancelled']));
    }

    public function search(): array
    {
        return ['sale_no'];
    }

    public function columns(): array
    {
        return ['Sale' => 'text', 'Date' => 'date', 'Project' => 'text', 'Unit' => 'text', 'Customer' => 'text', 'Price' => 'money', 'Discount' => 'money', 'Net Contract' => 'money', 'Status' => 'text'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = FlatSale::query()->visibleTo($user)->whereIn('status', [...FlatSale::ACTIVE, 'cancelled'])->with(['project:id,code', 'flat:id,flat_no', 'customer:id,full_name']);
        if ($project = self::project($request, $user)) {
            $query->where('project_id', $project->id);
        }
        if (array_key_exists($s = (string) $request->input('status'), $this->statuses())) {
            $query->where('status', $s);
        }

        return $this->inPeriod($query, $request, 'agreement_date')->orderBy('agreement_date')->orderBy('id');
    }

    public function row(mixed $s): array
    {
        return [$s->sale_no, $s->agreement_date, $s->project->code, $s->flat->flat_no, $s->customer->full_name, $s->sale_price, $s->discount_amount, $s->net_contract_value, FlatSale::STATUSES[$s->status]];
    }

    public function totals(Builder $query): ?array
    {
        $active = (clone $query)->whereIn('status', FlatSale::ACTIVE);

        return ['Active total', null, null, null, null, null, null, Money::amount((string) $active->sum('net_contract_value')), null];
    }
}
