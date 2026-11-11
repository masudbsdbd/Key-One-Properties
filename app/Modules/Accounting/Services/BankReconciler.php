<?php

namespace App\Modules\Accounting\Services;

use App\Models\BankReconciliation;
use App\Models\JournalLine;
use App\Models\MoneyAccount;
use App\Models\User;
use App\Support\Format;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Ticking the entries of an account off against the bank's own statement. */
class BankReconciler
{
    public function start(MoneyAccount $account, CarbonInterface $date, string $statementBalance, User $user): BankReconciliation
    {
        return DB::transaction(function () use ($account, $date, $statementBalance, $user) {
            MoneyAccount::query()->lockForUpdate()->findOrFail($account->id);
            if (BankReconciliation::query()->where('money_account_id', $account->id)->where('status', 'draft')->exists()) {
                throw ValidationException::withMessages(['statement_date' => 'Finish the reconciliation already in progress first.']);
            }
            $last = BankReconciliation::query()->where('money_account_id', $account->id)->where('status', 'completed')->max('statement_date');
            if ($last && $date->toDateString() <= $last) {
                throw ValidationException::withMessages(['statement_date' => 'The statement must be dated after '.Carbon::parse($last)->format('j M Y').'.']);
            }

            return BankReconciliation::query()->create([
                'money_account_id' => $account->id, 'statement_date' => $date->toDateString(), 'statement_balance' => Money::amount($statementBalance), 'created_by' => $user->id,
            ]);
        });
    }

    /** Entries that can be ticked: posted, dated up to the statement, and not ticked in an earlier reconciliation. */
    public function lines(BankReconciliation $reconciliation): Builder
    {
        return JournalLine::query()->select('journal_lines.*', 'journal_entries.accounting_date')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')->where('journal_lines.money_account_id', $reconciliation->money_account_id)
            ->where('journal_entries.accounting_date', '<=', $reconciliation->statement_date->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('journal_lines.bank_reconciliation_id')->orWhere('journal_lines.bank_reconciliation_id', $reconciliation->id));
    }

    /** @param array<int, int> $lineIds */
    public function tick(BankReconciliation $reconciliation, array $lineIds, bool $ticked): void
    {
        DB::transaction(function () use ($reconciliation, $lineIds, $ticked) {
            $reconciliation = BankReconciliation::query()->lockForUpdate()->findOrFail($reconciliation->id);
            Approval::ensureStatus($reconciliation->status, 'draft', 'This reconciliation is already completed.');
            // Query builder update: a posted line may only change its reconciliation mark.
            DB::table('journal_lines')->whereIn('id', $this->lines($reconciliation)->whereIn('journal_lines.id', $lineIds)->pluck('journal_lines.id'))
                ->update(['bank_reconciliation_id' => $ticked ? $reconciliation->id : null]);
        });
    }

    /**
     * @return array{statement: string, ticked: string, difference: string, book: string, open_count: int, open_amount: string}
     */
    public function summary(BankReconciliation $reconciliation): array
    {
        $net = fn (Builder $q) => Money::amount($q->select(DB::raw('COALESCE(SUM(journal_lines.debit) - SUM(journal_lines.credit), 0) AS net'))->value('net'));
        $done = BankReconciliation::query()->where('money_account_id', $reconciliation->money_account_id)->where('status', 'completed')->pluck('id')->push($reconciliation->id);
        $ticked = $net(JournalLine::query()->whereIn('bank_reconciliation_id', $done));
        $open = $this->lines($reconciliation)->whereNull('journal_lines.bank_reconciliation_id');

        return [
            'statement' => Money::amount($reconciliation->statement_balance),
            'ticked' => $ticked,
            'difference' => Money::amount(Money::sub((string) $reconciliation->statement_balance, $ticked)),
            'book' => Balances::of($reconciliation->moneyAccount->ledgerAccount, $reconciliation->statement_date->toDateString()),
            'open_count' => (clone $open)->count(),
            'open_amount' => $net(clone $open),
        ];
    }

    public function complete(BankReconciliation $reconciliation, User $user): BankReconciliation
    {
        return DB::transaction(function () use ($reconciliation, $user) {
            $reconciliation = BankReconciliation::query()->lockForUpdate()->findOrFail($reconciliation->id);
            Approval::ensureStatus($reconciliation->status, 'draft', 'This reconciliation is already completed.');
            $summary = $this->summary($reconciliation);
            if (Money::cmp($summary['difference'], 0) !== 0) {
                throw ValidationException::withMessages(['reconciliation' => 'The ticked entries differ from the statement by '.Format::money($summary['difference']).'.']);
            }
            $reconciliation->forceFill(['status' => 'completed', 'book_balance' => $summary['book'], 'completed_by' => $user->id, 'completed_at' => now()])->save();

            return $reconciliation;
        });
    }

    public function discard(BankReconciliation $reconciliation): void
    {
        DB::transaction(function () use ($reconciliation) {
            $reconciliation = BankReconciliation::query()->lockForUpdate()->findOrFail($reconciliation->id);
            Approval::ensureStatus($reconciliation->status, 'draft', 'A completed reconciliation cannot be removed.');
            DB::table('journal_lines')->where('bank_reconciliation_id', $reconciliation->id)->update(['bank_reconciliation_id' => null]);
            $reconciliation->delete();
        });
    }
}
