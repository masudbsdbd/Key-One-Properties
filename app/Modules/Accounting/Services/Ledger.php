<?php

namespace App\Modules\Accounting\Services;

use App\Models\FinancialEvent;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\MoneyAccount;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of journal entries and lines.
 *
 * A line is ['account' => LedgerAccount|system key, 'debit' => '0.00', 'credit' => '0.00'] plus optional
 * description and the dimension ids (project_id, flat_id, customer_id, supplier_id, contractor_id,
 * investor_id, money_account_id).
 */
class Ledger
{
    public const DIMENSIONS = ['project_id', 'flat_id', 'customer_id', 'supplier_id', 'contractor_id', 'investor_id', 'money_account_id'];

    /** Accounts whose lines must name a project. */
    private const NEEDS_PROJECT = ['wip'];

    /**
     * Create and post a journal in one step (automatic postings, transfers, money in and out).
     * With a source key, a repeat returns the journal posted the first time.
     *
     * @param  array{type: string, date: CarbonInterface, narration: string, reference?: ?string, source_key?: ?string, event?: ?FinancialEvent, source?: ?Model, project_id?: ?int}  $header
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function post(array $header, array $lines, ?User $user = null): JournalEntry
    {
        $key = $header['source_key'] ?? null;
        if ($key && ($existing = JournalEntry::query()->where('source_key', $key)->first())) {
            return $existing;
        }
        $lines = $this->prepare($lines);

        try {
            return DB::transaction(function () use ($header, $lines, $user, $key) {
                $period = Periods::ensureOpen($header['date'], lock: true);
                $entry = JournalEntry::query()->create([
                    'journal_no' => DocumentNumber::next('JV', (int) $header['date']->format('Y')),
                    'journal_type' => $header['type'],
                    'source_key' => $key,
                    'financial_event_id' => ($header['event'] ?? null)?->id,
                    'source_type' => ($header['source'] ?? null)?->getMorphClass(),
                    'source_id' => ($header['source'] ?? null)?->getKey(),
                    'accounting_date' => $header['date']->toDateString(),
                    'accounting_period_id' => $period->id,
                    'narration' => mb_substr($header['narration'], 0, 500),
                    'reference' => $header['reference'] ?? null,
                    'project_id' => $header['project_id'] ?? null,
                    'reversal_of_id' => $header['reversal_of_id'] ?? null,
                    'status' => 'draft',
                    'total_amount' => $this->total($lines),
                    'created_by' => $user?->id,
                ]);
                $this->writeLines($entry, $lines);
                $entry->forceFill(['status' => 'posted', 'posted_by' => $user?->id, 'posted_at' => now()])->save();

                return $entry;
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($key && ($existing = JournalEntry::query()->where('source_key', $key)->first())) {
                return $existing;
            }
            throw $e;
        }
    }

    /**
     * @param  array{date: CarbonInterface, narration: string, reference?: ?string}  $header
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function saveDraft(?JournalEntry $entry, array $header, array $lines, User $user): JournalEntry
    {
        $lines = $this->prepare($lines);

        return DB::transaction(function () use ($entry, $header, $lines, $user) {
            if ($entry) {
                $entry = JournalEntry::query()->lockForUpdate()->findOrFail($entry->id);
                Approval::ensureStatus($entry->status, ['draft', 'rejected'], 'Only a draft journal can be edited.');
                $entry->lines()->delete();
            } else {
                $entry = new JournalEntry(['journal_no' => DocumentNumber::next('JV', (int) $header['date']->format('Y')), 'journal_type' => 'manual', 'created_by' => $user->id]);
            }
            $entry->fill([
                'accounting_date' => $header['date']->toDateString(),
                'narration' => $header['narration'],
                'reference' => $header['reference'] ?? null,
                'status' => 'draft',
                'status_reason' => null,
                'total_amount' => $this->total($lines),
                'lock_version' => ($entry->lock_version ?? 0) + 1,
            ])->save();
            $this->writeLines($entry, $lines);

            return $entry;
        });
    }

    public function submit(JournalEntry $entry, User $user): JournalEntry
    {
        return $this->move($entry, ['draft', 'rejected'], function (JournalEntry $entry) use ($user) {
            Periods::ensureOpen($entry->accounting_date);
            $entry->forceFill(['status' => 'submitted', 'submitted_by' => $user->id, 'submitted_at' => now(), 'status_reason' => null])->save();
        });
    }

    public function approve(JournalEntry $entry, User $user): JournalEntry
    {
        return $this->move($entry, 'submitted', function (JournalEntry $entry) use ($user) {
            Approval::ensureNotSelf($user, $entry->submitted_by ?? $entry->created_by, 'a journal');
            $entry->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    public function reject(JournalEntry $entry, string $reason, User $user): JournalEntry
    {
        return $this->move($entry, ['submitted', 'approved'], function (JournalEntry $entry) use ($reason, $user) {
            $entry->forceFill(['status' => 'rejected', 'status_reason' => $reason, 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    public function postDraft(JournalEntry $entry, User $user): JournalEntry
    {
        return $this->move($entry, 'approved', function (JournalEntry $entry) use ($user) {
            $this->prepare($entry->lines()->with('account')->get()->map(fn (JournalLine $l) => ['account' => $l->account] + $l->only(['debit', 'credit', ...self::DIMENSIONS]))->all());
            $period = Periods::ensureOpen($entry->accounting_date, lock: true);
            $entry->forceFill(['status' => 'posted', 'accounting_period_id' => $period->id, 'posted_by' => $user->id, 'posted_at' => now()])->save();
        });
    }

    /** Post the opposite of a posted journal, dated $date, and link the two. */
    public function reverse(JournalEntry $entry, CarbonInterface $date, string $reason, ?User $user = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $date, $reason, $user) {
            $entry = JournalEntry::query()->lockForUpdate()->findOrFail($entry->id);
            Approval::ensureStatus($entry->status, 'posted', 'Only a posted journal can be reversed.');
            if ($entry->isReversed() || $entry->reversal_of_id) {
                throw ValidationException::withMessages(['journal' => $entry->isReversed() ? 'This journal is already reversed.' : 'A reversal cannot be reversed.']);
            }
            $lines = $entry->lines()->get()->map(fn (JournalLine $l) => ['account' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit, 'description' => $l->description] + $l->only(self::DIMENSIONS))->all();
            $reversal = $this->post([
                'type' => 'reversal', 'date' => $date, 'narration' => 'Reversal of '.$entry->journal_no.': '.$reason, 'reference' => $entry->reference,
                'source_key' => 'reversal:'.$entry->id, 'source' => $entry->source, 'project_id' => $entry->project_id, 'reversal_of_id' => $entry->id,
            ], $lines, $user);
            $entry->forceFill(['reversed_by_id' => $reversal->id])->save();

            return $reversal;
        });
    }

    /**
     * Drop empty lines, resolve accounts and enforce the double-entry rules.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function prepare(array $lines): array
    {
        $clean = [];
        $debit = $credit = '0';
        foreach (array_values($lines) as $i => $line) {
            $dr = Money::amount($line['debit'] ?? 0);
            $cr = Money::amount($line['credit'] ?? 0);
            if (Money::cmp($dr, 0) === 0 && Money::cmp($cr, 0) === 0) {
                continue;
            }
            if (Money::cmp($dr, 0) < 0 || Money::cmp($cr, 0) < 0 || (Money::isPositive($dr) && Money::isPositive($cr))) {
                throw ValidationException::withMessages(["lines.$i.debit" => 'Enter either a debit or a credit, not both.']);
            }
            $account = $this->account($line['account'] ?? null);
            if (! $account || ! $account->is_postable || ! $account->is_active) {
                throw ValidationException::withMessages(["lines.$i.account" => $account ? $account->label().' cannot be posted to.' : 'Select an account.']);
            }
            if (in_array($account->system_key, self::NEEDS_PROJECT, true) && empty($line['project_id'])) {
                throw ValidationException::withMessages(["lines.$i.project" => $account->name.' needs a project.']);
            }
            $debit = Money::add($debit, $dr);
            $credit = Money::add($credit, $cr);
            $dimensions = array_map(fn ($v) => $v ?: null, array_intersect_key($line, array_flip(self::DIMENSIONS)));
            // A line on a cash or bank ledger account always names its money account, so statements are complete.
            $dimensions['money_account_id'] = $this->moneyAccountId($account) ?? ($dimensions['money_account_id'] ?? null);
            $clean[] = ['account_id' => $account->id, 'debit' => $dr, 'credit' => $cr, 'description' => $line['description'] ?? null] + $dimensions;
        }
        if (count($clean) < 2) {
            throw ValidationException::withMessages(['lines' => 'A journal needs at least two lines.']);
        }
        if (Money::cmp($debit, $credit) !== 0) {
            throw ValidationException::withMessages(['lines' => 'Debits ('.Money::amount($debit).') and credits ('.Money::amount($credit).') differ by '.Money::amount(Money::sub($debit, $credit)).'.']);
        }

        return $clean;
    }

    private function moneyAccountId(LedgerAccount $account): ?int
    {
        return MoneyAccount::query()->where('ledger_account_id', $account->id)->value('id');
    }

    private function account(mixed $account): ?LedgerAccount
    {
        return match (true) {
            $account instanceof LedgerAccount => $account,
            is_int($account) => LedgerAccount::query()->find($account),
            is_string($account) && $account !== '' => LedgerAccount::forKey($account),
            default => null,
        };
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function total(array $lines): string
    {
        return Money::amount(Money::add('0', ...array_column($lines, 'debit')));
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function writeLines(JournalEntry $entry, array $lines): void
    {
        foreach ($lines as $i => $line) {
            JournalLine::query()->create(['journal_entry_id' => $entry->id, 'line_no' => $i + 1] + $line);
        }
    }

    /** @param array<int, string>|string $from */
    private function move(JournalEntry $entry, array|string $from, callable $change): JournalEntry
    {
        return DB::transaction(function () use ($entry, $from, $change) {
            $entry = JournalEntry::query()->lockForUpdate()->findOrFail($entry->id);
            Approval::ensureStatus($entry->status, $from, 'This journal is '.strtolower(JournalEntry::STATUSES[$entry->status]).'.');
            $change($entry);

            return $entry;
        });
    }

    public static function today(): Carbon
    {
        return Carbon::now(config('app.display_timezone'))->startOfDay();
    }
}
