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

/** Money paid out to investors: profit and capital returned, straight from the investor ledger. */
class InvestorPayoutRegisterReport extends BaseReport
{
    private const KIND = 'COALESCE(o.entry_kind, e.entry_kind)';

    public function key(): string
    {
        return 'investor-payouts';
    }

    public function name(): string
    {
        return 'Investor Payout Register';
    }

    public function group(): string
    {
        return 'investors';
    }

    public function basis(): string
    {
        return 'Posted profit payments and capital returns by date; a reversal shows as a negative line';
    }

    public function filters(): array
    {
        return ['project', 'period', 'investor'];
    }

    public function columns(): array
    {
        return ['Date' => 'date', 'Investor' => 'text', 'Unit' => 'text', 'Entry' => 'text', 'Details' => 'text', 'Profit Paid' => 'money', 'Capital Returned' => 'money'];
    }

    public function query(Request $request, User $user): Builder
    {
        $query = InvestorLedgerEntry::query()->from('investor_ledger_entries as e')->leftJoin('investor_ledger_entries as o', 'o.id', '=', 'e.reversal_of_id')
            ->join('investors', 'investors.id', '=', 'e.investor_id')->join('flats', 'flats.id', '=', 'e.flat_id')->join('projects', 'projects.id', '=', 'flats.project_id')
            ->whereIn('e.flat_id', Flat::query()->visibleTo($user)->select('flats.id'))->whereRaw(self::KIND." IN ('profit_paid', 'capital_out')")
            ->selectRaw('e.id, e.entry_date, e.entry_kind, e.description, e.amount, investors.name AS investor_name, projects.code AS project_code, flats.flat_no, '.self::KIND.' AS kind');
        if ($project = self::project($request, $user)) {
            $query->where('flats.project_id', $project->id);
        }
        if ($investor = Investor::findByPublicId($request->input('investor'))) {
            $query->where('e.investor_id', $investor->id);
        }

        return $this->inPeriod($query, $request, 'e.entry_date')->orderByDesc('e.entry_date')->orderByDesc('e.id');
    }

    public function row(mixed $r): array
    {
        $profit = $r->kind === 'profit_paid';

        return [$r->entry_date, $r->investor_name, $r->project_code.' · '.$r->flat_no, ($profit ? 'Profit Paid' : 'Capital Returned').($r->entry_kind === 'reversal' ? ' (reversed)' : ''), $r->description,
            $profit ? $r->amount : null, $profit ? null : $r->amount];
    }

    public function totals(Builder $query): ?array
    {
        $rows = (clone $query)->reorder()->get();
        $sum = fn (string $kind) => Money::amount(Money::add('0', ...$rows->where('kind', $kind)->map(fn ($r) => (string) $r->amount)->all()));

        return ['Total', null, null, null, null, $sum('profit_paid'), $sum('capital_out')];
    }
}
