<?php

namespace App\Modules\Accounting\Services;

use App\Models\LedgerAccount;
use App\Models\MoneyAccount;
use App\Models\MoneyTransaction;
use App\Models\User;
use App\Modules\Accounting\Support\ChartOfAccounts;
use App\Support\DocumentNumber;
use App\Support\Format;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashBank
{
    /** Kinds that cannot go below zero (a bank account may be overdrawn). */
    private const NO_OVERDRAFT = ['cash', 'mobile'];

    public function __construct(private Ledger $ledger) {}

    /**
     * @param  array{kind: string, name: string, bank_name?: ?string, account_number?: ?string, branch?: ?string, notes?: ?string, opening_balance?: ?string, opening_date?: ?CarbonInterface}  $data
     */
    public function createAccount(array $data, User $user): MoneyAccount
    {
        return DB::transaction(function () use ($data, $user) {
            $parent = LedgerAccount::query()->where('code', ChartOfAccounts::MONEY_PARENTS[$data['kind']])->lockForUpdate()->firstOrFail();
            $last = (int) LedgerAccount::query()->where('parent_id', $parent->id)->max(DB::raw('CAST(code AS UNSIGNED)'));
            $code = (string) (max($last, (int) $parent->code) + 1);
            while (LedgerAccount::query()->where('code', $code)->exists()) {
                $code = (string) ((int) $code + 1);
            }
            $ledger = new LedgerAccount(['code' => $code, 'name' => $data['name'], 'category' => 'asset', 'is_postable' => true, 'is_active' => true]);
            $ledger->parent_id = $parent->id;
            $ledger->is_system = true;
            $ledger->created_by = $user->id;
            $ledger->save();

            $account = new MoneyAccount(array_intersect_key($data, array_flip(['kind', 'name', 'bank_name', 'account_number', 'branch', 'notes'])));
            $account->ledger_account_id = $ledger->id;
            $account->created_by = $user->id;
            $account->save();

            if (Money::isPositive($data['opening_balance'] ?? null)) {
                $this->ledger->post([
                    'type' => 'opening', 'date' => $data['opening_date'], 'narration' => 'Opening balance of '.$account->name,
                    'source_key' => 'opening:money:'.$account->id, 'source' => $account,
                ], [['account' => $ledger, 'debit' => $data['opening_balance']], ['account' => 'opening_equity', 'credit' => $data['opening_balance']]], $user);
            }

            return $account;
        });
    }

    public function updateAccount(MoneyAccount $account, array $data): MoneyAccount
    {
        return DB::transaction(function () use ($account, $data) {
            $account->fill(array_intersect_key($data, array_flip(['name', 'bank_name', 'account_number', 'branch', 'notes', 'is_active'])))->save();
            $account->ledgerAccount->forceFill(['name' => $account->name, 'is_active' => $account->is_active])->save();

            return $account;
        });
    }

    public function deleteAccount(MoneyAccount $account): void
    {
        if ($account->isUsed()) {
            throw ValidationException::withMessages(['account' => $account->name.' has transactions and cannot be deleted. Make it inactive instead.']);
        }
        DB::transaction(function () use ($account) {
            $ledger = $account->ledgerAccount;
            $account->delete();
            $ledger->delete();
        });
    }

    public function balance(MoneyAccount $account, ?string $to = null): string
    {
        return Balances::of($account->ledgerAccount, $to);
    }

    /**
     * Move money between two of the company's own accounts.
     *
     * @param  array{amount: string, charge?: ?string, date: CarbonInterface, reference?: ?string, description?: ?string, key?: ?string}  $data
     */
    public function transfer(MoneyAccount $from, MoneyAccount $to, array $data, User $user): MoneyTransaction
    {
        if ($from->is($to)) {
            throw ValidationException::withMessages(['other' => 'Choose two different accounts.']);
        }
        $charge = Money::amount($data['charge'] ?? 0);

        return $this->record('transfer', $data, $user, ['from_money_account_id' => $from->id, 'to_money_account_id' => $to->id, 'charge_amount' => $charge],
            function () use ($from, $to, $data, $charge) {
                $this->ensureFunds($from, Money::add($data['amount'], $charge));

                return ['transfer', 'Transfer from '.$from->name.' to '.$to->name, array_filter([
                    ['account' => $to->ledgerAccount, 'debit' => $data['amount']],
                    ['account' => $from->ledgerAccount, 'credit' => $data['amount']],
                    Money::isPositive($charge) ? ['account' => 'bank_charges', 'debit' => $charge, 'description' => 'Transfer charge'] : null,
                    Money::isPositive($charge) ? ['account' => $from->ledgerAccount, 'credit' => $charge, 'description' => 'Transfer charge'] : null,
                ])];
            });
    }

    /**
     * Money that enters or leaves the company outside the other modules (owner, loans, charges, wages…).
     *
     * @param  array{amount: string, date: CarbonInterface, purpose: string, counter?: ?LedgerAccount, party_name?: ?string, reference?: ?string, description?: ?string, project_id?: ?int, key?: ?string}  $data
     */
    public function move(string $direction, MoneyAccount $account, array $data, User $user): MoneyTransaction
    {
        $in = $direction === 'money_in';
        $purposes = $in ? MoneyTransaction::IN_PURPOSES : MoneyTransaction::OUT_PURPOSES;
        [$label, $key] = $purposes[$data['purpose']] ?? throw ValidationException::withMessages(['other' => 'Choose where the money '.($in ? 'comes from' : 'goes').'.']);
        $counter = $key ? LedgerAccount::forKey($key) : ($data['counter'] ?? null);
        if (! $counter || $counter->moneyAccount()->exists()) {
            throw ValidationException::withMessages(['counter' => 'Select the account this belongs to.']);
        }

        return $this->record($direction, $data, $user, [
            $in ? 'to_money_account_id' : 'from_money_account_id' => $account->id, 'purpose' => $data['purpose'], 'counter_account_id' => $counter->id,
            'party_name' => $data['party_name'] ?? null, 'project_id' => $data['project_id'] ?? null,
        ], function () use ($in, $account, $data, $counter, $label) {
            if (! $in) {
                $this->ensureFunds($account, $data['amount']);
            }
            $other = ['account' => $counter, $in ? 'credit' : 'debit' => $data['amount'], 'project_id' => $data['project_id'] ?? null];
            $own = ['account' => $account->ledgerAccount, $in ? 'debit' : 'credit' => $data['amount']];
            $narration = $label.(($data['party_name'] ?? null) ? ' · '.$data['party_name'] : '').' ('.$account->name.')';

            return [$in ? 'receipt' : 'payment', $narration, $in ? [$own, $other] : [$other, $own]];
        });
    }

    /** Cancel a posted transaction with a reversing journal dated today. */
    public function cancel(MoneyTransaction $txn, string $reason, User $user): MoneyTransaction
    {
        return DB::transaction(function () use ($txn, $reason, $user) {
            $txn = MoneyTransaction::query()->lockForUpdate()->findOrFail($txn->id);
            Approval::ensureStatus($txn->status, 'posted', 'This transaction is already cancelled.');
            $date = Ledger::today()->max($txn->txn_date);
            if ($txn->to_money_account_id) {
                $this->ensureFunds($txn->to, (string) $txn->amount, 'Cancelling this would take '.$txn->to->name.' below zero.');
            }
            $this->ledger->reverse($txn->journal, $date, $reason, $user);
            $txn->forceFill(['status' => 'reversed', 'reversed_by' => $user->id, 'reversed_at' => now(), 'reversal_reason' => $reason])->save();

            return $txn;
        });
    }

    /**
     * @param  callable(): array{0: string, 1: string, 2: array<int, array<string, mixed>>}  $build
     */
    private function record(string $type, array $data, User $user, array $attributes, callable $build): MoneyTransaction
    {
        if (! empty($data['key']) && ($existing = MoneyTransaction::query()->where('idempotency_key', $data['key'])->first())) {
            return $existing;
        }

        return DB::transaction(function () use ($type, $data, $user, $attributes, $build) {
            // Serialise movements per account so two withdrawals cannot both pass the funds check.
            MoneyAccount::query()->whereIn('id', array_filter([$attributes['from_money_account_id'] ?? null, $attributes['to_money_account_id'] ?? null]))->orderBy('id')->lockForUpdate()->get();
            [$journalType, $narration, $lines] = $build();

            $txn = MoneyTransaction::query()->create($attributes + [
                'txn_no' => DocumentNumber::next('MT', (int) $data['date']->format('Y')),
                'type' => $type,
                'amount' => Money::amount($data['amount']),
                'txn_date' => $data['date']->toDateString(),
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'idempotency_key' => $data['key'] ?? null,
                'created_by' => $user->id,
            ]);
            $journal = $this->ledger->post([
                'type' => $journalType, 'date' => $data['date'], 'narration' => $narration.(($data['description'] ?? null) ? ': '.$data['description'] : ''),
                'reference' => $data['reference'] ?? null, 'source_key' => 'money:'.$txn->id, 'source' => $txn, 'project_id' => $attributes['project_id'] ?? null,
            ], array_values($lines), $user);
            $txn->forceFill(['journal_entry_id' => $journal->id])->save();

            return $txn;
        });
    }

    private function ensureFunds(MoneyAccount $account, string $amount, ?string $message = null): void
    {
        if (! in_array($account->kind, self::NO_OVERDRAFT, true)) {
            return;
        }
        $balance = $this->balance($account);
        if (Money::cmp($balance, $amount) < 0) {
            throw ValidationException::withMessages(['amount' => $message ?? $account->name.' has only '.Format::money($balance).'.']);
        }
    }
}
