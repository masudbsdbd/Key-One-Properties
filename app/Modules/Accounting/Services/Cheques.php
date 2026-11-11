<?php

namespace App\Modules\Accounting\Services;

use App\Models\Cheque;
use App\Models\CustomerPayment;
use App\Models\JournalEntry;
use App\Models\MoneyAccount;
use App\Models\User;
use App\Modules\Collections\Services\PaymentService;
use App\Support\Workflow\Approval;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cheques received from customers: In Hand → Deposited → Cleared, or Bounced.
 * Until a cheque clears, its money sits in Cheques in Hand, not in a bank account.
 */
class Cheques
{
    public function __construct(private Ledger $ledger) {}

    /**
     * @param  array{cheque_no: string, bank_name?: ?string, cheque_date?: ?string, status: string, money_account_id?: ?int}  $data
     */
    public function record(CustomerPayment $payment, array $data, User $user): Cheque
    {
        $settled = in_array($data['status'], ['cleared', 'bounced'], true);

        return Cheque::query()->create([
            'source_type' => $payment->getMorphClass(), 'source_id' => $payment->id, 'customer_id' => $payment->customer_id,
            'cheque_no' => $data['cheque_no'], 'bank_name' => $data['bank_name'] ?? null, 'cheque_date' => $data['cheque_date'] ?? null,
            'amount' => $payment->amount, 'status' => $data['status'],
            'money_account_id' => in_array($data['status'], ['deposited', 'cleared'], true) ? ($data['money_account_id'] ?? null) : null,
            'received_on' => $payment->payment_date,
            'deposited_on' => in_array($data['status'], ['deposited', 'cleared'], true) ? $payment->payment_date : null,
            'settled_on' => $settled ? $payment->payment_date : null,
            'created_by' => $user->id,
        ]);
    }

    public function deposit(Cheque $cheque, MoneyAccount $account, CarbonInterface $date, User $user): Cheque
    {
        return $this->change($cheque, ['in_hand'], function (Cheque $cheque) use ($account, $date, $user) {
            $this->ensureBank($account);
            $cheque->forceFill(['status' => 'deposited', 'money_account_id' => $account->id, 'deposited_on' => $date->toDateString(), 'updated_by' => $user->id])->save();
        });
    }

    /** The bank paid the cheque: the money moves from Cheques in Hand to the bank account. */
    public function clear(Cheque $cheque, MoneyAccount $account, CarbonInterface $date, User $user): Cheque
    {
        return $this->change($cheque, Cheque::OPEN, function (Cheque $cheque) use ($account, $date, $user) {
            $this->ensureBank($account);
            $cheque->forceFill([
                'status' => 'cleared', 'money_account_id' => $account->id, 'deposited_on' => $cheque->deposited_on ?? $date->toDateString(),
                'settled_on' => $date->toDateString(), 'updated_by' => $user->id,
            ])->save();
            $payment = $cheque->source;
            // A payment not posted yet goes straight to the bank when it posts.
            if ($payment instanceof CustomerPayment && $this->heldInHand($payment)) {
                $dims = ['customer_id' => $payment->customer_id, 'project_id' => $payment->project_id];
                $this->ledger->post([
                    'type' => 'system', 'date' => $date, 'narration' => 'Cheque '.$cheque->cheque_no.' cleared · '.$payment->payment_no,
                    'reference' => $cheque->cheque_no, 'source_key' => 'cheque:cleared:'.$cheque->id, 'source' => $cheque, 'project_id' => $payment->project_id,
                ], [
                    ['account' => $account->ledgerAccount, 'debit' => (string) $cheque->amount] + $dims,
                    ['account' => 'cheques_in_hand', 'credit' => (string) $cheque->amount] + $dims,
                ], $user);
            }
        });
    }

    /** The cheque was returned unpaid: the customer's payment is taken back. */
    public function bounce(Cheque $cheque, CarbonInterface $date, ?string $note, User $user): Cheque
    {
        return $this->change($cheque, Cheque::OPEN, function (Cheque $cheque) use ($date, $note, $user) {
            $cheque->forceFill(['status' => 'bounced', 'settled_on' => $date->toDateString(), 'status_note' => $note, 'updated_by' => $user->id])->save();
            $payment = $cheque->source;
            if ($payment instanceof CustomerPayment) {
                $reason = 'Cheque '.$cheque->cheque_no.' bounced'.($note ? ': '.$note : '');
                match ($payment->status) {
                    'posted' => app(PaymentService::class)->reverse($payment, $reason, $user),
                    'pending' => app(PaymentService::class)->reject($payment, $reason, $user),
                    default => null,
                };
            }
        });
    }

    /** A payment was reversed by hand: its cheque no longer counts, and a clearing journal is taken back. */
    public function paymentReversed(CustomerPayment $payment, string $reason, User $user): void
    {
        $cheque = Cheque::query()->where('source_type', $payment->getMorphClass())->where('source_id', $payment->id)->lockForUpdate()->first();
        if (! $cheque || $cheque->status === 'bounced') {
            return;
        }
        $clearing = JournalEntry::query()->where('source_key', 'cheque:cleared:'.$cheque->id)->whereNull('reversed_by_id')->first();
        if ($clearing) {
            $this->ledger->reverse($clearing, Ledger::today()->max($clearing->accounting_date), $reason, $user);
        }
        $cheque->forceFill(['status' => 'bounced', 'settled_on' => Ledger::today()->toDateString(), 'status_note' => $reason, 'updated_by' => $user->id])->save();
    }

    /** True when the payment's posted journal put the money in Cheques in Hand. */
    private function heldInHand(CustomerPayment $payment): bool
    {
        return JournalEntry::query()->posted()->whereNull('reversed_by_id')->whereNull('reversal_of_id')
            ->where('source_type', $payment->getMorphClass())->where('source_id', $payment->id)
            ->whereHas('lines', fn ($q) => $q->where('debit', '>', 0)->whereHas('account', fn ($a) => $a->where('system_key', 'cheques_in_hand')))->exists();
    }

    private function ensureBank(MoneyAccount $account): void
    {
        if ($account->kind !== 'bank' || ! $account->is_active) {
            throw ValidationException::withMessages(['money_account' => 'Choose an active bank account.']);
        }
    }

    /** @param array<int, string> $from */
    private function change(Cheque $cheque, array $from, callable $apply): Cheque
    {
        return DB::transaction(function () use ($cheque, $from, $apply) {
            $cheque = Cheque::query()->with('source')->lockForUpdate()->findOrFail($cheque->id);
            Approval::ensureStatus($cheque->status, $from, 'This cheque is already '.strtolower(Cheque::STATUSES[$cheque->status]).'.');
            $apply($cheque);

            return $cheque;
        });
    }
}
