<?php

namespace App\Modules\Accounting\Services;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PeriodControl
{
    public function __construct(private Ledger $ledger) {}

    public function lock(AccountingPeriod $period, User $user): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $user) {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);
            Approval::ensureStatus($period->status, 'open', $period->name.' is already '.$period->status.'.');
            $this->ensureNothingOpen($period->start_date->toDateString(), $period->end_date->toDateString(), $period->name);
            $period->forceFill(['status' => 'locked', 'locked_by' => $user->id, 'locked_at' => now(), 'status_reason' => null])->save();

            return $period;
        });
    }

    public function unlock(AccountingPeriod $period, string $reason, User $user): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $reason, $user) {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->id);
            Approval::ensureStatus($period->status, 'locked', $period->status === 'closed' ? 'Reopen the fiscal year first.' : $period->name.' is not locked.');
            $period->forceFill(['status' => 'open', 'locked_by' => $user->id, 'locked_at' => now(), 'status_reason' => $reason])->save();

            return $period;
        });
    }

    /**
     * Close a fiscal year: move the year's profit or loss to Retained Earnings and close every month.
     * Income and expense accounts start the next year at zero.
     */
    public function closeYear(FiscalYear $year, User $user): FiscalYear
    {
        return DB::transaction(function () use ($year, $user) {
            $year = FiscalYear::query()->lockForUpdate()->findOrFail($year->id);
            Approval::ensureStatus($year->status, 'open', $year->name.' is already closed.');
            if (FiscalYear::query()->where('status', 'open')->where('start_date', '<', $year->start_date)->exists()) {
                throw ValidationException::withMessages(['year' => 'Close the earlier fiscal year first.']);
            }
            [$from, $to] = [$year->start_date->toDateString(), $year->end_date->toDateString()];
            $this->ensureNothingOpen($from, $to, $year->name);

            $net = Balances::byAccount(null, $to);
            $lines = [];
            $profit = '0';
            foreach (LedgerAccount::query()->whereIn('category', ['income', 'expense'])->whereIn('id', $net->keys())->get() as $account) {
                $balance = $net[$account->id];
                if (Money::cmp($balance, 0) === 0) {
                    continue;
                }
                // Opposite of the balance brings the account to zero.
                $lines[] = ['account' => $account, Money::cmp($balance, 0) > 0 ? 'credit' : 'debit' => Money::cmp($balance, 0) > 0 ? $balance : Money::neg($balance)];
                $profit = Money::sub($profit, $balance);
            }
            $journal = null;
            if ($lines !== []) {
                $lines[] = ['account' => 'retained_earnings', Money::cmp($profit, 0) >= 0 ? 'credit' : 'debit' => Money::cmp($profit, 0) >= 0 ? $profit : Money::neg($profit)];
                // The last month is opened for this one entry, then everything is closed.
                AccountingPeriod::query()->where('fiscal_year_id', $year->id)->where('end_date', $to)->update(['status' => 'open']);
                $journal = $this->ledger->post(['type' => 'closing', 'date' => $year->end_date, 'narration' => 'Year closing '.$year->name, 'source_key' => 'closing:'.$year->id.':'.Str::uuid(), 'source' => $year], $lines, $user);
            }
            AccountingPeriod::query()->where('fiscal_year_id', $year->id)->update(['status' => 'closed', 'locked_by' => $user->id, 'locked_at' => now()]);
            $year->forceFill(['status' => 'closed', 'closed_by' => $user->id, 'closed_at' => now(), 'closing_journal_id' => $journal?->id])->save();

            return $year;
        });
    }

    public function reopenYear(FiscalYear $year, string $reason, User $user): FiscalYear
    {
        return DB::transaction(function () use ($year, $reason, $user) {
            $year = FiscalYear::query()->lockForUpdate()->findOrFail($year->id);
            Approval::ensureStatus($year->status, 'closed', $year->name.' is not closed.');
            if (FiscalYear::query()->where('status', 'closed')->where('start_date', '>', $year->start_date)->exists()) {
                throw ValidationException::withMessages(['year' => 'Reopen the later fiscal year first.']);
            }
            $closing = $year->closing_journal_id ? JournalEntry::query()->find($year->closing_journal_id) : null;
            AccountingPeriod::query()->where('fiscal_year_id', $year->id)->update(['status' => 'locked', 'status_reason' => $reason]);
            if ($closing && ! $closing->isReversed()) {
                AccountingPeriod::query()->where('fiscal_year_id', $year->id)->where('end_date', $year->end_date->toDateString())->update(['status' => 'open']);
                $this->ledger->reverse($closing, $year->end_date, $reason, $user);
                AccountingPeriod::query()->where('fiscal_year_id', $year->id)->where('end_date', $year->end_date->toDateString())->update(['status' => 'locked']);
            }
            $year->forceFill(['status' => 'open', 'closed_by' => $user->id, 'closed_at' => now(), 'closing_journal_id' => null])->save();

            return $year;
        });
    }

    /** Nothing half-done may be left in a month that is about to be locked. */
    private function ensureNothingOpen(string $from, string $to, string $name): void
    {
        $journals = JournalEntry::query()->whereIn('status', ['draft', 'submitted', 'approved'])->whereBetween('accounting_date', [$from, $to])->count();
        if ($journals) {
            throw ValidationException::withMessages(['period' => $journals.' journals of '.$name.' are not posted yet.']);
        }
        $events = EventPoster::backlog()->whereBetween('effective_at', [$from, $to])->count();
        if ($events) {
            throw ValidationException::withMessages(['period' => $events.' transactions of '.$name.' are waiting to be posted.']);
        }
    }
}
