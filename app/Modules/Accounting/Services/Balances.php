<?php

namespace App\Modules\Accounting\Services;

use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Sums of posted journal lines. Every figure on a report comes from here. */
class Balances
{
    /**
     * Posted lines, optionally limited by date and dimensions.
     *
     * @param  array<string, int|null>  $where  column => id (project_id, flat_id, money_account_id, …)
     */
    public static function lines(?string $from = null, ?string $to = null, array $where = []): Builder
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->when($from, fn (Builder $q) => $q->where('journal_entries.accounting_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('journal_entries.accounting_date', '<=', $to))
            ->when(array_filter($where), fn (Builder $q) => $q->where(collect($where)->filter()->mapWithKeys(fn ($v, $k) => ['journal_lines.'.$k => $v])->all()));
    }

    /**
     * Debit minus credit per account.
     *
     * @return Collection<int, string> account id => net debit
     */
    public static function byAccount(?string $from = null, ?string $to = null, array $where = []): Collection
    {
        return self::lines($from, $to, $where)->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, SUM(journal_lines.debit) - SUM(journal_lines.credit) AS net')
            ->pluck('net', 'account_id')->map(fn ($net) => Money::amount($net));
    }

    /** Balance in the account's own direction: positive is the normal side. */
    public static function of(LedgerAccount $account, ?string $to = null, array $where = [], ?string $from = null): string
    {
        $net = Money::amount(self::lines($from, $to, $where)->where('journal_lines.account_id', $account->id)
            ->selectRaw('COALESCE(SUM(journal_lines.debit) - SUM(journal_lines.credit), 0) AS net')->value('net'));

        return $account->isDebitNormal() ? $net : Money::neg($net);
    }

    public static function natural(LedgerAccount $account, string $netDebit): string
    {
        return $account->isDebitNormal() ? Money::amount($netDebit) : Money::amount(Money::neg($netDebit));
    }
}
