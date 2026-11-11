<?php

namespace App\Modules\Reports\Reports;

use App\Models\FlatSale;
use App\Models\Project;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Dates;
use App\Support\Money;
use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** One line per live sale: what the customer agreed, has paid, still owes, and how much of it is late. */
class CustomerOutstandingReport extends BaseReport
{
    private const ENTRIES = 'customer_statement_entries s WHERE s.sale_id = flat_sales.id OR (s.sale_id IS NULL AND flat_sales.booking_id IS NOT NULL AND s.booking_id = flat_sales.booking_id)';

    private const ITEMS = "installment_schedule_items i JOIN installment_plans pl ON pl.id = i.plan_id WHERE pl.sale_id = flat_sales.id AND pl.status = 'active' AND i.status <> 'rescheduled'
        AND i.due_date < ? AND i.principal_due + i.fee_due - i.principal_paid - i.fee_paid > 0";

    public function key(): string
    {
        return 'customer-outstanding';
    }

    public function name(): string
    {
        return 'Customer Outstanding';
    }

    public function group(): string
    {
        return 'collections';
    }

    public function basis(): string
    {
        return 'Live sales; balance from the customer statement, overdue from installments past their due date';
    }

    public function filters(): array
    {
        return ['project', 'status'];
    }

    public function statuses(): array
    {
        return ['balance' => 'With Balance', 'overdue' => 'Overdue'];
    }

    public function search(): array
    {
        return ['flat_sales.sale_no'];
    }

    public function columns(): array
    {
        return ['Customer' => 'text', 'Mobile' => 'text', 'Unit' => 'text', 'Sale' => 'text', 'Contract Value' => 'money', 'Received' => 'money',
            'Balance' => 'money', 'Overdue' => 'money', 'Days Late' => 'number'];
    }

    public function query(Request $request, User $user): Builder
    {
        $today = Dates::today()->toDateString();
        $query = FlatSale::query()->whereIn('flat_sales.status', FlatSale::ACTIVE)->whereIn('flat_sales.project_id', Project::query()->visibleTo($user)->select('projects.id'))
            ->with(['customer:id,full_name,primary_mobile', 'flat:id,flat_no', 'project:id,code'])
            ->select('flat_sales.*')
            ->selectRaw('(SELECT COALESCE(SUM(s.credit_amount), 0) FROM '.self::ENTRIES.') AS received_amount')
            ->selectRaw('(SELECT COALESCE(SUM(s.charge_amount - s.credit_amount), 0) FROM '.self::ENTRIES.') AS balance_amount')
            ->selectRaw('(SELECT COALESCE(SUM(i.principal_due + i.fee_due - i.principal_paid - i.fee_paid), 0) FROM '.self::ITEMS.') AS overdue_amount', [$today])
            ->selectRaw('(SELECT MIN(i.due_date) FROM '.self::ITEMS.') AS oldest_due', [$today]);
        if ($project = self::project($request, $user)) {
            $query->where('flat_sales.project_id', $project->id);
        }
        match ((string) $request->input('status')) {
            'balance' => $query->whereRaw('(SELECT COALESCE(SUM(s.charge_amount - s.credit_amount), 0) FROM '.self::ENTRIES.') > 0'),
            'overdue' => $query->whereRaw('EXISTS (SELECT 1 FROM '.self::ITEMS.')', [$today]),
            default => null,
        };

        return $query->orderByDesc('overdue_amount')->orderByDesc('balance_amount');
    }

    public function row(mixed $s): array
    {
        return [$s->customer->full_name, Phone::display($s->customer->primary_mobile), $s->project->code.' · '.$s->flat->flat_no, $s->sale_no, $s->net_contract_value,
            Money::amount((string) $s->received_amount), Money::amount((string) $s->balance_amount), Money::amount((string) $s->overdue_amount),
            $s->oldest_due ? (int) Carbon::parse($s->oldest_due)->diffInDays(Dates::today()) : null];
    }

    public function totals(Builder $query): ?array
    {
        $rows = (clone $query)->reorder()->get();
        $sum = fn (string $field) => Money::amount(Money::add('0', ...$rows->map(fn ($r) => (string) $r->{$field})->all()));

        return ['Total', null, null, null, $sum('net_contract_value'), $sum('received_amount'), $sum('balance_amount'), $sum('overdue_amount'), null];
    }
}
