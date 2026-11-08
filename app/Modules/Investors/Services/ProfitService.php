<?php

namespace App\Modules\Investors\Services;

use App\Models\Flat;
use App\Models\FlatHandover;
use App\Models\FlatProfitCalculation;
use App\Models\FlatRegistration;
use App\Models\FlatSale;
use App\Models\InvestorFlatAgreement;
use App\Models\InvestorProfitAllocation;
use App\Models\User;
use App\Modules\Collections\Services\CustomerStatement;
use App\Support\DocumentNumber;
use App\Support\FinancialEvents\FinancialEventRecorder;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Flat profit = contract revenue − direct cost − shared cost (latest approved run) − other cost.
 * Investor amounts are rounded down to the paisa; the company keeps the explicit remainder.
 * A loss gives investors zero; they are never charged. Approval posts only the change against what
 * was already approved for that investor and unit, so recalculations never double count.
 */
class ProfitService
{
    public function __construct(private ProfitPolicy $policy, private InvestorLedger $ledger, private FinancialEventRecorder $events) {}

    public static function activeSale(Flat $flat): ?FlatSale
    {
        return FlatSale::query()->where('flat_id', $flat->id)->whereIn('status', FlatSale::ACTIVE)->latest('id')->first();
    }

    /** @return array{eligible: bool, basis: string, detail: string} */
    public function eligibility(FlatSale $sale): array
    {
        $basis = $this->policy->revenueRecognition();
        $sale->loadMissing(['registration', 'handover', 'customer']);
        [$eligible, $detail] = match ($basis) {
            null => [false, 'Revenue recognition policy is not set'],
            'sale_approved' => [$sale->status !== 'cancel_requested', 'Sale '.FlatSale::STATUSES[$sale->status]],
            'registered' => [$sale->registration?->status === 'registered', 'Registration '.($sale->registration ? FlatRegistration::STATUSES[$sale->registration->status] : 'not started')],
            'handed_over' => [$sale->handover?->status === 'completed', 'Handover '.($sale->handover ? FlatHandover::STATUSES[$sale->handover->status] : 'not started')],
            'fully_collected' => [Money::cmp(CustomerStatement::balance($sale->customer, $sale->id), '0') <= 0, 'Customer balance '.CustomerStatement::balance($sale->customer, $sale->id)],
        };
        if ($sale->status === 'cancel_requested') {
            [$eligible, $detail] = [false, 'Sale cancellation is pending'];
        }

        return ['eligible' => $eligible, 'basis' => $basis ?? 'not_configured', 'detail' => $detail];
    }

    /** Agreements of the unit active on the date. */
    public static function agreementsOn(Flat $flat, Carbon $date): Collection
    {
        return InvestorFlatAgreement::query()->where('flat_id', $flat->id)->where('status', 'active')->with('investor:id,public_id,name,investor_code')
            ->orderBy('id')->get()->filter(fn (InvestorFlatAgreement $a) => $a->coversDate($date))->values();
    }

    public static function investorAmount(string $net, string $share): string
    {
        if (! Money::isPositive($net)) {
            return '0.00';
        }

        return (string) BigDecimal::of($net)->multipliedBy($share)->dividedBy(100, 2, RoundingMode::Down);
    }

    public function calculate(Flat $flat, array $data, User $user): FlatProfitCalculation
    {
        return DB::transaction(function () use ($flat, $data, $user) {
            $flat = Flat::query()->lockForUpdate()->findOrFail($flat->id);
            if (FlatProfitCalculation::query()->where('flat_id', $flat->id)->whereIn('status', ['draft', 'submitted'])->exists()) {
                throw ValidationException::withMessages(['flat' => 'This unit already has an open calculation.']);
            }
            $sale = self::activeSale($flat) ?? throw ValidationException::withMessages(['flat' => 'This unit has no active sale.']);
            $run = CostAllocationService::latestApproved($flat->project_id) ?? throw ValidationException::withMessages(['flat' => 'Approve a cost allocation run for this project first.']);
            $asOf = Carbon::parse($data['as_of']);
            $cost = CostAllocationService::flatCost($run, $flat->id);
            $other = Money::amount($data['other_cost'] ?? '0');
            $revenue = Money::amount((string) $sale->net_contract_value);
            $net = Money::amount(Money::sub(Money::sub(Money::sub($revenue, $cost['direct']), $cost['shared']), $other));
            $eligibility = $this->eligibility($sale);
            $agreements = self::agreementsOn($flat, $asOf);
            $share = Money::rate(Money::add('0', ...$agreements->map(fn ($a) => (string) $a->profit_share_percent)->all()));
            $amounts = $agreements->mapWithKeys(fn ($a) => [$a->id => self::investorAmount($net, (string) $a->profit_share_percent)]);
            $investorTotal = Money::amount(Money::add('0', ...$amounts->values()->all()));

            $calc = FlatProfitCalculation::query()->create([
                'calculation_no' => DocumentNumber::next('FPC'), 'project_id' => $flat->project_id, 'flat_id' => $flat->id, 'sale_id' => $sale->id, 'cost_run_id' => $run->id,
                'revenue_amount' => $revenue, 'direct_cost_amount' => $cost['direct'], 'shared_cost_amount' => $cost['shared'], 'other_approved_cost_amount' => $other,
                'net_profit_amount' => $net, 'investor_share_percent' => $share,
                'company_share_amount' => Money::isPositive($net) ? Money::amount(Money::sub($net, $investorTotal)) : '0.00',
                'recognition_basis' => $eligibility['basis'], 'revenue_eligible' => $eligibility['eligible'],
                'basis_snapshot' => [
                    'as_of' => $asOf->toDateString(), 'sale_no' => $sale->sale_no, 'sale_status' => $sale->status, 'run_no' => $run->run_no, 'run_version' => $run->calculation_version,
                    'cost_categories' => $cost['categories'], 'other_cost_note' => $data['other_cost_note'] ?? null, 'eligibility' => $eligibility['detail'],
                    'cost_policy' => $this->policy->costPolicy(), 'agreements' => $agreements->map(fn ($a) => ['id' => $a->id, 'no' => $a->agreement_no, 'share' => (string) $a->profit_share_percent])->all(),
                ],
                'status' => 'draft', 'calculated_by' => $user->id,
                'version' => FlatProfitCalculation::query()->where('flat_id', $flat->id)->count() + 1,
            ]);
            foreach ($agreements as $a) {
                $previous = InvestorLedger::balances($a->investor, $flat->id)['payable'];
                InvestorProfitAllocation::query()->create([
                    'profit_calculation_id' => $calc->id, 'investor_agreement_id' => $a->id, 'investor_id' => $a->investor_id, 'flat_id' => $flat->id,
                    'share_percent_snapshot' => $a->profit_share_percent, 'profit_amount' => $amounts[$a->id], 'previously_approved' => $previous,
                    'payable_change' => Money::amount(Money::sub($amounts[$a->id], $previous)), 'status' => 'proposed',
                ]);
            }

            return $calc;
        });
    }

    public function submit(FlatProfitCalculation $calc, User $user): void
    {
        DB::transaction(function () use ($calc, $user) {
            $calc = FlatProfitCalculation::query()->lockForUpdate()->findOrFail($calc->id);
            Approval::ensureStatus($calc->status, 'draft', 'Only draft calculations can be submitted.');
            $calc->forceFill(['status' => 'submitted', 'submitted_by' => $user->id])->save();
        });
    }

    /** Why approval is not possible right now, or null. */
    public function approvalBlocker(FlatProfitCalculation $calc): ?string
    {
        if ($reason = $this->policy->blockingReason()) {
            return $reason;
        }
        $calc->loadMissing(['sale', 'flat', 'costRun']);
        if ($calc->sale === null || ! in_array($calc->sale->status, FlatSale::ACTIVE, true)) {
            return 'The sale is no longer active';
        }
        if (! $this->eligibility($calc->sale)['eligible']) {
            return 'Revenue is not eligible yet: '.$this->eligibility($calc->sale)['detail'];
        }
        if (CostAllocationService::latestApproved($calc->project_id)?->id !== $calc->cost_run_id) {
            return 'A newer cost allocation run was approved; recalculate';
        }
        if ($calc->costRun?->isStale()) {
            return 'Cost changed after the cost allocation run; run allocation again';
        }
        $now = self::agreementsOn($calc->flat, Carbon::parse($calc->basis_snapshot['as_of']))->map(fn ($a) => $a->id.':'.$a->profit_share_percent)->all();
        $then = collect($calc->basis_snapshot['agreements'] ?? [])->map(fn ($a) => $a['id'].':'.Money::rate($a['share']))->all();
        if ($now !== $then) {
            return 'Investor agreements changed; recalculate';
        }

        return null;
    }

    public function approve(FlatProfitCalculation $calc, User $user): void
    {
        DB::transaction(function () use ($calc, $user) {
            Flat::query()->lockForUpdate()->findOrFail($calc->flat_id);
            $calc = FlatProfitCalculation::query()->with(['flat', 'project'])->lockForUpdate()->findOrFail($calc->id);
            Approval::ensureStatus($calc->status, 'submitted', 'Only submitted calculations can be approved.');
            Approval::ensureNotSelf($user, $calc->calculated_by, 'a profit calculation');
            if ($blocker = $this->approvalBlocker($calc)) {
                throw ValidationException::withMessages(['calculation' => $blocker.'.']);
            }

            $total = '0';
            foreach ($calc->allocations()->with('investor')->lockForUpdate()->get() as $allocation) {
                $balances = InvestorLedger::balances($allocation->investor, $calc->flat_id);
                if (Money::cmp((string) $allocation->profit_amount, $balances['paid']) < 0) {
                    throw ValidationException::withMessages(['calculation' => $allocation->investor->name.' was already paid more than this profit.']);
                }
                $change = Money::amount(Money::sub((string) $allocation->profit_amount, $balances['payable']));
                if (Money::cmp($change, '0') !== 0) {
                    $this->ledger->post($allocation->investor, 'profit_payable', $allocation, now(config('app.display_timezone')), $change,
                        'Profit '.$calc->calculation_no.' · Unit '.$calc->flat->flat_no, $allocation->investor_agreement_id, $calc->flat_id);
                }
                $allocation->forceFill(['previously_approved' => $balances['payable'], 'payable_change' => $change, 'status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
                $total = Money::add($total, $change);
            }
            FlatProfitCalculation::query()->where('flat_id', $calc->flat_id)->where('status', 'approved')->update(['status' => 'superseded', 'updated_at' => now()]);
            $calc->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
            $this->events->record('investors', $calc, 'profit.payable_approved', Money::amount($total), now(config('app.display_timezone')),
                ['calculation_no' => $calc->calculation_no, 'net_profit' => (string) $calc->net_profit_amount, 'company_share' => (string) $calc->company_share_amount],
                project: $calc->project, flat: $calc->flat, user: $user);
        });
    }

    public function reject(FlatProfitCalculation $calc, string $reason, User $user): void
    {
        DB::transaction(function () use ($calc, $reason, $user) {
            $calc = FlatProfitCalculation::query()->lockForUpdate()->findOrFail($calc->id);
            Approval::ensureStatus($calc->status, 'submitted', 'Only submitted calculations can be rejected.');
            $calc->forceFill(['status' => 'rejected', 'status_reason' => $reason, 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    public function cancel(FlatProfitCalculation $calc, string $reason, User $user): void
    {
        DB::transaction(function () use ($calc, $reason) {
            $calc = FlatProfitCalculation::query()->lockForUpdate()->findOrFail($calc->id);
            Approval::ensureStatus($calc->status, ['draft', 'submitted'], 'Only open calculations can be cancelled.');
            $calc->forceFill(['status' => 'cancelled', 'status_reason' => $reason])->save();
        });
    }
}
