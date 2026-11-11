<?php

namespace App\Modules\Investors\Services;

use App\Models\Flat;
use App\Models\Investor;
use App\Models\InvestorPayout;
use App\Models\InvestorProfitDistribution;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\FinancialEvents\FinancialEventRecorder;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approved payable → distribution request → approval → payout. Approval is not payment:
 * only a posted payout moves money and writes profit_paid to the investor ledger.
 */
class DistributionService
{
    public function __construct(private InvestorLedger $ledger, private FinancialEventRecorder $events) {}

    /** Payable not yet paid and not already requested (pending or approved distributions). */
    public static function available(Investor $investor, int $flatId, ?int $exceptId = null): string
    {
        $balances = InvestorLedger::balances($investor, $flatId);
        $open = InvestorProfitDistribution::query()->where('investor_id', $investor->id)->where('flat_id', $flatId)
            ->whereIn('status', ['pending_approval', 'approved'])->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->pluck('amount')->map(fn ($v) => (string) $v)->all();

        return Money::amount(Money::sub($balances['profit_due'], Money::add('0', ...$open)));
    }

    public function request(Investor $investor, Flat $flat, string $amount, User $user, string $key): InvestorProfitDistribution
    {
        return DB::transaction(function () use ($investor, $flat, $amount, $user, $key) {
            if ($existing = InvestorProfitDistribution::query()->where('idempotency_key', $key)->first()) {
                return $existing;
            }
            Investor::query()->lockForUpdate()->findOrFail($investor->id);
            $this->ensureAvailable($investor, $flat->id, Money::amount($amount));

            return InvestorProfitDistribution::query()->create([
                'distribution_no' => DocumentNumber::next('ID'), 'investor_id' => $investor->id, 'flat_id' => $flat->id, 'amount' => Money::amount($amount),
                'status' => 'pending_approval', 'requested_by' => $user->id, 'idempotency_key' => $key,
            ]);
        });
    }

    public function approve(InvestorProfitDistribution $distribution, User $user): void
    {
        DB::transaction(function () use ($distribution, $user) {
            Investor::query()->lockForUpdate()->findOrFail($distribution->investor_id);
            $d = InvestorProfitDistribution::query()->with(['investor', 'flat.project'])->lockForUpdate()->findOrFail($distribution->id);
            Approval::ensureStatus($d->status, 'pending_approval', 'This distribution was already decided.');
            Approval::ensureNotSelf($user, $d->requested_by, 'a distribution');
            $this->ensureAvailable($d->investor, $d->flat_id, (string) $d->amount, $d->id);
            $d->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
            $this->events->record('investors', $d, 'profit.distribution_approved', (string) $d->amount, now(config('app.display_timezone')),
                ['distribution_no' => $d->distribution_no, 'investor' => $d->investor->investor_code], project: $d->flat->project, flat: $d->flat, user: $user);
        });
    }

    public function reject(InvestorProfitDistribution $distribution, string $reason, User $user): void
    {
        DB::transaction(function () use ($distribution, $reason, $user) {
            $d = InvestorProfitDistribution::query()->lockForUpdate()->findOrFail($distribution->id);
            Approval::ensureStatus($d->status, ['pending_approval', 'approved'], 'Only open distributions can be rejected.');
            $d->forceFill(['status' => 'rejected', 'status_reason' => $reason, 'approved_by' => $d->approved_by ?? $user->id])->save();
        });
    }

    public function pay(InvestorProfitDistribution $distribution, array $data, User $user, string $key): InvestorPayout
    {
        return DB::transaction(function () use ($distribution, $data, $user, $key) {
            if ($existing = InvestorPayout::query()->where('idempotency_key', $key)->first()) {
                return $existing;
            }
            Investor::query()->lockForUpdate()->findOrFail($distribution->investor_id);
            $d = InvestorProfitDistribution::query()->with(['investor', 'flat.project'])->lockForUpdate()->findOrFail($distribution->id);
            Approval::ensureStatus($d->status, 'approved', 'Only approved distributions can be paid.');
            if (Money::cmp((string) $d->amount, InvestorLedger::balances($d->investor, $d->flat_id)['profit_due']) > 0) {
                throw ValidationException::withMessages(['amount' => 'The profit due is lower than this distribution.']);
            }
            $payout = InvestorPayout::query()->create([
                'payout_no' => DocumentNumber::next('IP'), 'distribution_id' => $d->id, 'investor_id' => $d->investor_id, 'amount' => $d->amount,
                'paid_at' => $data['paid_at'], 'payment_method' => $data['payment_method'], 'money_account_id' => $data['money_account_id'] ?? null, 'payment_reference' => $data['payment_reference'] ?? null,
                'status' => 'posted', 'posted_by' => $user->id, 'idempotency_key' => $key,
            ]);
            $d->forceFill(['status' => 'paid'])->save();
            $this->ledger->post($d->investor, 'profit_paid', $payout, $payout->paid_at, (string) $payout->amount,
                'Profit paid '.$payout->payout_no.' · Unit '.$d->flat->flat_no, null, $d->flat_id);
            $this->events->record('investors', $payout, 'profit.distribution_paid', (string) $payout->amount, $payout->paid_at,
                ['payout_no' => $payout->payout_no, 'distribution_no' => $d->distribution_no, 'method' => $payout->payment_method], project: $d->flat->project, flat: $d->flat, user: $user);

            return $payout;
        });
    }

    public function reversePayout(InvestorPayout $payout, string $reason, User $user): void
    {
        DB::transaction(function () use ($payout, $reason, $user) {
            $payout = InvestorPayout::query()->lockForUpdate()->findOrFail($payout->id);
            Approval::ensureStatus($payout->status, 'posted', 'Only posted payouts can be reversed.');
            $d = InvestorProfitDistribution::query()->lockForUpdate()->findOrFail($payout->distribution_id);
            $payout->forceFill(['status' => 'reversed', 'status_reason' => $reason, 'reversed_at' => now()])->save();
            $d->forceFill(['status' => 'approved'])->save();
            $this->ledger->reverse($payout, 'profit_paid', $reason);
            $this->events->reverse('investors', $payout, now(config('app.display_timezone')), $user);
        });
    }

    private function ensureAvailable(Investor $investor, int $flatId, string $amount, ?int $exceptId = null): void
    {
        $available = self::available($investor, $flatId, $exceptId);
        if (! Money::isPositive($amount) || Money::cmp($amount, $available) > 0) {
            throw ValidationException::withMessages(['amount' => 'Available to distribute: '.Money::amount(Money::cmp($available, '0') > 0 ? $available : '0').'.']);
        }
    }
}
