<?php

namespace App\Modules\Investors\Services;

use App\Models\Flat;
use App\Models\Investor;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorFlatAgreement;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\FinancialEvents\FinancialEventRecorder;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Investors, flat agreements (profit shares never above 100% at any date) and capital transactions.
 * Capital and profit stay separate balances in the investor ledger.
 */
class InvestorService
{
    public const MAX_SHARE = '100.0000';

    public function __construct(private InvestorLedger $ledger, private FinancialEventRecorder $events) {}

    public function saveInvestor(?Investor $investor, array $data, User $user): Investor
    {
        return DB::transaction(function () use ($investor, $data, $user) {
            $investor ??= new Investor;
            if (! $investor->exists) {
                $investor->forceFill(['investor_code' => Investor::nextCode(), 'created_by' => $user->id]);
            }
            $investor->fill($data)->save();

            return $investor;
        });
    }

    /** Active agreements of a flat, optionally excluding some (the one being edited and the version it replaces). */
    public static function activeAgreements(Flat $flat, array $excludeIds = []): Collection
    {
        return InvestorFlatAgreement::query()->where('flat_id', $flat->id)->where('status', 'active')
            ->when($excludeIds, fn ($q) => $q->whereNotIn('id', $excludeIds))->with('investor:id,public_id,name,investor_code')->orderBy('effective_from')->get();
    }

    /** Highest total share held by the given agreements on any date inside [from, to]. */
    public static function peakShare(Collection $agreements, CarbonInterface $from, ?CarbonInterface $to): string
    {
        $overlapping = $agreements->filter(fn (InvestorFlatAgreement $a) => ($a->effective_to === null || $a->effective_to->gte($from)) && ($to === null || $a->effective_from->lte($to)));
        $points = $overlapping->map(fn ($a) => $a->effective_from->gt($from) ? $a->effective_from : $from)->push($from)->unique(fn ($d) => $d->toDateString());

        $peak = '0';
        foreach ($points as $point) {
            $sum = Money::add('0', ...$overlapping->filter(fn ($a) => $a->coversDate($point))->map(fn ($a) => (string) $a->profit_share_percent)->values()->all());
            if (Money::cmp($sum, $peak) > 0) {
                $peak = $sum;
            }
        }

        return Money::rate($peak);
    }

    /**
     * Share summary for the allocation gauge.
     *
     * @return array{allocated: string, available: string, agreements: Collection}
     */
    public static function shareSummary(Flat $flat, CarbonInterface $from, ?CarbonInterface $to, array $excludeIds = []): array
    {
        $agreements = self::activeAgreements($flat, $excludeIds);
        $allocated = self::peakShare($agreements, $from, $to);

        return ['allocated' => $allocated, 'available' => Money::rate(Money::sub(self::MAX_SHARE, $allocated)), 'agreements' => $agreements];
    }

    public function saveAgreement(?InvestorFlatAgreement $agreement, Investor $investor, Flat $flat, array $data, User $user): InvestorFlatAgreement
    {
        return DB::transaction(function () use ($agreement, $investor, $flat, $data, $user) {
            if ($agreement) {
                $agreement = InvestorFlatAgreement::query()->lockForUpdate()->findOrFail($agreement->id);
                Approval::ensureStatus($agreement->status, ['draft', 'rejected'], 'Only draft agreements can be edited.');
                if ($agreement->supersedes_id && ($agreement->investor_id !== $investor->id || $agreement->flat_id !== $flat->id)) {
                    throw ValidationException::withMessages(['flat' => 'An amendment keeps the same investor and unit.']);
                }
            } else {
                $agreement = new InvestorFlatAgreement(['agreement_no' => DocumentNumber::next('IA'), 'created_by' => $user->id, 'status' => 'draft', 'version' => 1]);
            }
            if ($investor->status !== 'active') {
                throw ValidationException::withMessages(['investor' => 'This investor is inactive.']);
            }
            $agreement->forceFill([
                'investor_id' => $investor->id, 'project_id' => $flat->project_id, 'flat_id' => $flat->id, 'investment_scope' => 'flat',
                'committed_capital' => Money::amount($data['committed_capital']), 'profit_share_percent' => Money::rate($data['profit_share_percent']),
                'effective_from' => $data['effective_from'], 'effective_to' => $data['effective_to'] ?? null, 'terms' => $data['terms'] ?? null,
            ])->save();
            $this->ensureShareFits($agreement, Flat::query()->findOrFail($flat->id));

            return $agreement;
        });
    }

    /** New draft version of an active agreement; the old one stays active until the new one is approved. */
    public function amend(InvestorFlatAgreement $agreement, User $user): InvestorFlatAgreement
    {
        return DB::transaction(function () use ($agreement, $user) {
            $agreement = InvestorFlatAgreement::query()->lockForUpdate()->findOrFail($agreement->id);
            Approval::ensureStatus($agreement->status, 'active', 'Only active agreements can be amended.');
            if (InvestorFlatAgreement::query()->where('supersedes_id', $agreement->id)->whereIn('status', ['draft', 'pending', 'rejected'])->exists()) {
                throw ValidationException::withMessages(['agreement' => 'An amendment of this agreement is already open.']);
            }

            return InvestorFlatAgreement::query()->create([
                'agreement_no' => DocumentNumber::next('IA'), 'investor_id' => $agreement->investor_id, 'project_id' => $agreement->project_id, 'flat_id' => $agreement->flat_id,
                'investment_scope' => 'flat', 'committed_capital' => $agreement->committed_capital, 'profit_share_percent' => $agreement->profit_share_percent,
                'effective_from' => now(config('app.display_timezone'))->toDateString(), 'effective_to' => $agreement->effective_to, 'terms' => $agreement->terms,
                'status' => 'draft', 'version' => $agreement->version + 1, 'supersedes_id' => $agreement->id, 'created_by' => $user->id,
            ]);
        });
    }

    public function submitAgreement(InvestorFlatAgreement $agreement, User $user): void
    {
        DB::transaction(function () use ($agreement) {
            $agreement = InvestorFlatAgreement::query()->lockForUpdate()->findOrFail($agreement->id);
            Approval::ensureStatus($agreement->status, ['draft', 'rejected'], 'Only draft agreements can be submitted.');
            $this->ensureShareFits($agreement, Flat::query()->findOrFail($agreement->flat_id));
            $agreement->forceFill(['status' => 'pending', 'status_reason' => null])->save();
        });
    }

    /** Locks the flat row so two approvals on one flat cannot both pass the 100% check. */
    public function approveAgreement(InvestorFlatAgreement $agreement, User $user): void
    {
        DB::transaction(function () use ($agreement, $user) {
            $agreement = InvestorFlatAgreement::query()->lockForUpdate()->findOrFail($agreement->id);
            Approval::ensureStatus($agreement->status, 'pending', 'Only agreements waiting for approval can be approved.');
            Approval::ensureNotSelf($user, $agreement->created_by, 'an agreement');
            $flat = Flat::query()->lockForUpdate()->findOrFail($agreement->flat_id);
            $this->ensureShareFits($agreement, $flat);

            if ($agreement->supersedes_id) {
                $old = InvestorFlatAgreement::query()->lockForUpdate()->findOrFail($agreement->supersedes_id);
                Approval::ensureStatus($old->status, 'active', 'The agreement being amended is no longer active.');
                $end = $agreement->effective_from->copy()->subDay();
                $old->forceFill([
                    'status' => 'closed', 'status_reason' => 'Replaced by '.$agreement->agreement_no, 'closed_by' => $user->id, 'closed_at' => now(),
                    'effective_to' => $end->lt($old->effective_from) ? $old->effective_from : $end,
                ])->save();
            }
            $agreement->forceFill(['status' => 'active', 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    public function rejectAgreement(InvestorFlatAgreement $agreement, string $reason, User $user): void
    {
        DB::transaction(function () use ($agreement, $reason, $user) {
            $agreement = InvestorFlatAgreement::query()->lockForUpdate()->findOrFail($agreement->id);
            Approval::ensureStatus($agreement->status, 'pending', 'Only agreements waiting for approval can be rejected.');
            $agreement->forceFill(['status' => 'rejected', 'status_reason' => $reason, 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    public function cancelAgreement(InvestorFlatAgreement $agreement, string $reason, User $user): void
    {
        DB::transaction(function () use ($agreement, $reason) {
            $agreement = InvestorFlatAgreement::query()->lockForUpdate()->findOrFail($agreement->id);
            Approval::ensureStatus($agreement->status, ['draft', 'pending', 'rejected'], 'Only agreements that are not active can be cancelled.');
            $agreement->forceFill(['status' => 'cancelled', 'status_reason' => $reason])->save();
        });
    }

    public function closeAgreement(InvestorFlatAgreement $agreement, string $date, string $reason, User $user): void
    {
        DB::transaction(function () use ($agreement, $date, $reason, $user) {
            $agreement = InvestorFlatAgreement::query()->lockForUpdate()->findOrFail($agreement->id);
            Approval::ensureStatus($agreement->status, 'active', 'Only active agreements can be closed.');
            if (Carbon::parse($date)->lt($agreement->effective_from)) {
                throw ValidationException::withMessages(['effective_to' => 'The end date cannot be before the start date.']);
            }
            if (InvestorCapitalTransaction::query()->where('agreement_id', $agreement->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['effective_to' => 'Post or reject the pending capital transactions first.']);
            }
            $agreement->forceFill(['status' => 'closed', 'status_reason' => $reason, 'effective_to' => $date, 'closed_by' => $user->id, 'closed_at' => now()])->save();
        });
    }

    private function ensureShareFits(InvestorFlatAgreement $agreement, Flat $flat): void
    {
        if (! Money::isPositive((string) $agreement->profit_share_percent) || Money::cmp((string) $agreement->profit_share_percent, self::MAX_SHARE) > 0) {
            throw ValidationException::withMessages(['profit_share_percent' => 'Share must be above 0 and at most 100%.']);
        }
        $summary = self::shareSummary($flat, $agreement->effective_from, $agreement->effective_to, array_filter([$agreement->id, $agreement->supersedes_id]));
        if (Money::cmp((string) $agreement->profit_share_percent, $summary['available']) > 0) {
            throw ValidationException::withMessages(['profit_share_percent' => 'Only '.rtrim(rtrim($summary['available'], '0'), '.').'% of this unit is free for these dates.']);
        }
    }

    /** Posted capital minus posted returns for an investor on a flat (all agreement versions). */
    public static function capitalBalance(int $investorId, int $flatId): string
    {
        $row = InvestorCapitalTransaction::query()->where('investor_id', $investorId)->where('flat_id', $flatId)->where('status', 'posted')
            ->selectRaw("COALESCE(SUM(CASE WHEN transaction_type = 'contribution' THEN amount ELSE 0 END),0) AS cin, COALESCE(SUM(CASE WHEN transaction_type = 'capital_return' THEN amount ELSE 0 END),0) AS cout")
            ->toBase()->first();

        return Money::amount(Money::sub((string) $row->cin, (string) $row->cout));
    }

    private static function pendingTotal(int $investorId, int $flatId, string $type): string
    {
        return Money::amount((string) InvestorCapitalTransaction::query()->where('investor_id', $investorId)->where('flat_id', $flatId)
            ->where('status', 'pending')->where('transaction_type', $type)->sum('amount'));
    }

    public function recordCapital(InvestorFlatAgreement $agreement, string $type, array $data, User $user, string $key): InvestorCapitalTransaction
    {
        return DB::transaction(function () use ($agreement, $type, $data, $user, $key) {
            if ($existing = InvestorCapitalTransaction::query()->where('idempotency_key', $key)->first()) {
                return $existing;
            }
            $agreement = InvestorFlatAgreement::query()->lockForUpdate()->findOrFail($agreement->id);
            $amount = Money::amount($data['amount']);
            if (! Money::isPositive($amount)) {
                throw ValidationException::withMessages(['amount' => 'Enter an amount above zero.']);
            }
            $this->ensureCapitalAllowed($agreement, $type, $amount, includePending: true);

            return InvestorCapitalTransaction::query()->create([
                'transaction_no' => DocumentNumber::next('IC'), 'investor_id' => $agreement->investor_id, 'agreement_id' => $agreement->id,
                'flat_id' => $agreement->flat_id, 'project_id' => $agreement->project_id, 'transaction_type' => $type, 'amount' => $amount,
                'transaction_date' => $data['transaction_date'], 'payment_method' => $data['payment_method'] ?? null, 'money_account_id' => $data['money_account_id'] ?? null, 'reference_no' => $data['reference_no'] ?? null,
                'notes' => $data['notes'] ?? null, 'status' => 'pending', 'created_by' => $user->id, 'idempotency_key' => $key,
            ]);
        });
    }

    public function postCapital(InvestorCapitalTransaction $tx, User $user): void
    {
        DB::transaction(function () use ($tx, $user) {
            $tx = InvestorCapitalTransaction::query()->lockForUpdate()->findOrFail($tx->id);
            Approval::ensureStatus($tx->status, 'pending', 'This transaction was already decided.');
            Approval::ensureNotSelf($user, $tx->created_by, 'a capital transaction');
            $agreement = InvestorFlatAgreement::query()->lockForUpdate()->findOrFail($tx->agreement_id);
            $this->ensureCapitalAllowed($agreement, $tx->transaction_type, (string) $tx->amount, includePending: false);

            $tx->forceFill(['status' => 'posted', 'approved_by' => $user->id, 'posted_at' => now()])->save();
            $tx->load(['investor', 'flat', 'project']);
            $in = $tx->transaction_type === 'contribution';
            $this->ledger->post($tx->investor, $in ? 'capital_in' : 'capital_out', $tx, $tx->transaction_date, (string) $tx->amount,
                ($in ? 'Capital ' : 'Capital return ').$tx->transaction_no.' · Unit '.$tx->flat->flat_no, $tx->agreement_id, $tx->flat_id);
            $this->events->record('investors', $tx, $in ? 'investment.received' : 'investment.withdrawn', (string) $tx->amount, $tx->transaction_date,
                ['transaction_no' => $tx->transaction_no, 'investor' => $tx->investor->investor_code, 'agreement' => $agreement->agreement_no, 'method' => $tx->payment_method],
                project: $tx->project, flat: $tx->flat, user: $user);
        });
    }

    public function rejectCapital(InvestorCapitalTransaction $tx, string $reason, User $user): void
    {
        DB::transaction(function () use ($tx, $reason, $user) {
            $tx = InvestorCapitalTransaction::query()->lockForUpdate()->findOrFail($tx->id);
            Approval::ensureStatus($tx->status, 'pending', 'This transaction was already decided.');
            $tx->forceFill(['status' => 'rejected', 'status_reason' => $reason, 'approved_by' => $user->id])->save();
        });
    }

    public function reverseCapital(InvestorCapitalTransaction $tx, string $reason, User $user): void
    {
        DB::transaction(function () use ($tx, $reason, $user) {
            $tx = InvestorCapitalTransaction::query()->lockForUpdate()->findOrFail($tx->id);
            Approval::ensureStatus($tx->status, 'posted', 'Only posted transactions can be reversed.');
            InvestorFlatAgreement::query()->lockForUpdate()->findOrFail($tx->agreement_id);
            if ($tx->transaction_type === 'contribution' && Money::cmp(self::capitalBalance($tx->investor_id, $tx->flat_id), (string) $tx->amount) < 0) {
                throw ValidationException::withMessages(['reason' => 'Capital already returned exceeds what would remain. Reverse the return first.']);
            }
            $tx->forceFill(['status' => 'reversed', 'status_reason' => $reason, 'reversed_at' => now()])->save();
            $this->ledger->reverse($tx, $tx->transaction_type === 'contribution' ? 'capital_in' : 'capital_out', $reason);
            $this->events->reverse('investors', $tx, now(config('app.display_timezone')), $user);
        });
    }

    private function ensureCapitalAllowed(InvestorFlatAgreement $agreement, string $type, string $amount, bool $includePending): void
    {
        $balance = self::capitalBalance($agreement->investor_id, $agreement->flat_id);
        if ($type === 'contribution') {
            Approval::ensureStatus($agreement->status, 'active', 'Capital can be received only on an active agreement.');
            $pending = $includePending ? self::pendingTotal($agreement->investor_id, $agreement->flat_id, 'contribution') : '0';
            $room = Money::sub(Money::sub((string) $agreement->committed_capital, $balance), $pending);
            if (Money::cmp($amount, $room) > 0) {
                throw ValidationException::withMessages(['amount' => 'Committed capital allows up to '.Money::amount(Money::cmp($room, '0') > 0 ? $room : '0').'.']);
            }

            return;
        }
        Approval::ensureStatus($agreement->status, ['active', 'closed'], 'Capital can be returned only on an active or closed agreement.');
        $pending = $includePending ? self::pendingTotal($agreement->investor_id, $agreement->flat_id, 'capital_return') : '0';
        $room = Money::sub($balance, $pending);
        if (Money::cmp($amount, $room) > 0) {
            throw ValidationException::withMessages(['amount' => 'Returnable capital is '.Money::amount(Money::cmp($room, '0') > 0 ? $room : '0').'.']);
        }
    }
}
