<?php

namespace App\Modules\Reports\Reports;

use App\Models\Flat;
use App\Models\Investor;
use App\Models\InvestorLedgerEntry;
use App\Models\User;
use App\Modules\Reports\BaseReport;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class InvestorPositionReport extends BaseReport
{
    public function key(): string
    {
        return 'investor-position';
    }

    public function name(): string
    {
        return 'Investor Capital and Profit';
    }

    public function group(): string
    {
        return 'investors';
    }

    public function basis(): string
    {
        return 'Posted ledger: approved payable and paid profit are separate; estimates excluded';
    }

    public function filters(): array
    {
        return ['project', 'investor'];
    }

    public function columns(): array
    {
        return ['Investor' => 'text', 'Project' => 'text', 'Unit' => 'text', 'Capital' => 'money', 'Profit Approved' => 'money', 'Profit Paid' => 'money', 'Profit Due' => 'money'];
    }

    public function query(Request $request, User $user): Builder
    {
        $kind = 'COALESCE(o.entry_kind, e.entry_kind)';
        $sum = fn (string $in, ?string $out = null) => "SUM(CASE WHEN {$kind} = '{$in}' THEN e.amount".($out ? " WHEN {$kind} = '{$out}' THEN -e.amount" : '').' ELSE 0 END)';
        $query = InvestorLedgerEntry::query()->from('investor_ledger_entries as e')->leftJoin('investor_ledger_entries as o', 'o.id', '=', 'e.reversal_of_id')
            ->join('investors', 'investors.id', '=', 'e.investor_id')->join('flats', 'flats.id', '=', 'e.flat_id')->join('projects', 'projects.id', '=', 'flats.project_id')
            ->whereIn('e.flat_id', Flat::query()->visibleTo($user)->select('flats.id'))
            ->selectRaw("investors.name AS investor_name, projects.code AS project_code, flats.flat_no, {$sum('capital_in', 'capital_out')} AS capital,
                {$sum('profit_payable')} AS payable, {$sum('profit_paid')} AS paid")
            ->groupBy('investors.name', 'projects.code', 'flats.flat_no');
        if ($project = self::project($request, $user)) {
            $query->where('flats.project_id', $project->id);
        }
        if ($investor = Investor::findByPublicId($request->input('investor'))) {
            $query->where('e.investor_id', $investor->id);
        }

        return $query->orderBy('investors.name')->orderBy('projects.code')->orderBy('flats.flat_no');
    }

    public function row(mixed $r): array
    {
        return [$r->investor_name, $r->project_code, $r->flat_no, Money::amount((string) $r->capital), Money::amount((string) $r->payable), Money::amount((string) $r->paid),
            Money::amount(Money::sub((string) $r->payable, (string) $r->paid))];
    }
}
