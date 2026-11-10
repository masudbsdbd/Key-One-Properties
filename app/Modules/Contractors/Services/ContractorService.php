<?php

namespace App\Modules\Contractors\Services;

use App\Models\Contractor;
use App\Models\ContractorBill;
use App\Models\ContractorMeasurement;
use App\Models\ContractorPayment;
use App\Models\ContractorWorkOrder;
use App\Models\ContractorWorkOrderVariation;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;
use App\Support\Costing\CostAttribution;
use App\Support\DocumentNumber;
use App\Support\FinancialEvents\FinancialEventRecorder;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Work order → measurement → running bill → payment → settlement.
 * Bill approval posts the contractor ledger charge and project cost; payments only settle.
 */
class ContractorService
{
    public function __construct(
        private ContractorLedger $ledger,
        private CostAttribution $costs,
        private FinancialEventRecorder $events,
    ) {}

    public function createContractor(array $data, User $user): Contractor
    {
        $contractor = new Contractor(Arr::only($data, (new Contractor)->getFillable()));
        $contractor->contractor_code = DocumentNumber::next('CON');
        $contractor->created_by = $user->id;
        $contractor->save();

        return $contractor;
    }

    public function saveWorkOrder(?ContractorWorkOrder $wo, Contractor $contractor, Project $project, ?WorkPackage $package, array $data, User $user): ContractorWorkOrder
    {
        return DB::transaction(function () use ($wo, $contractor, $project, $package, $data, $user) {
            if ($package && $package->project_id !== $project->id) {
                throw ValidationException::withMessages(['work_package' => 'Select a work package of this project.']);
            }
            if ($wo) {
                $wo = ContractorWorkOrder::query()->lockForUpdate()->findOrFail($wo->id);
                Approval::ensureStatus($wo->status, 'draft', 'Only draft work orders can be edited.');
            } else {
                $wo = new ContractorWorkOrder(['wo_no' => DocumentNumber::next('WO'), 'status' => 'draft', 'created_by' => $user->id]);
            }

            $wo->fill([
                'contractor_id' => $contractor->id,
                'project_id' => $project->id,
                'work_package_id' => $package?->id,
                'title' => $data['title'],
                'scope' => $data['scope'] ?? null,
                'contract_amount' => Money::amount($data['contract_amount']),
                'retention_percent' => Money::amount($data['retention_percent'] ?? '0'),
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
            ])->save();

            return $wo;
        });
    }

    public function approveWorkOrder(ContractorWorkOrder $wo, User $user): void
    {
        DB::transaction(function () use ($wo, $user) {
            $wo = ContractorWorkOrder::query()->lockForUpdate()->findOrFail($wo->id);
            Approval::ensureStatus($wo->status, 'draft', 'This work order is no longer a draft.');
            Approval::ensureNotSelf($user, $wo->created_by, 'a work order');
            $wo->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    public function cancelWorkOrder(ContractorWorkOrder $wo, string $reason, User $user): void
    {
        DB::transaction(function () use ($wo, $reason) {
            $wo = ContractorWorkOrder::query()->lockForUpdate()->findOrFail($wo->id);
            Approval::ensureStatus($wo->status, ['draft', 'approved'], 'This work order cannot be cancelled.');
            if ($wo->bills()->whereIn('status', ['draft', 'approved', 'paid'])->exists() || $wo->payments()->whereIn('status', ['pending', 'approved'])->exists()) {
                throw new ConflictHttpException('This work order already has bills or payments.');
            }
            $wo->measurements()->where('status', 'draft')->update(['status' => 'rejected', 'rejection_reason' => 'Work order cancelled']);
            $wo->forceFill(['status' => 'cancelled', 'status_reason' => $reason])->save();
        });
    }

    /** A variation revises the contract value; it never goes below what is already measured. */
    public function requestVariation(ContractorWorkOrder $wo, string $newAmount, string $reason, User $user): ContractorWorkOrderVariation
    {
        return DB::transaction(function () use ($wo, $newAmount, $reason, $user) {
            $wo = ContractorWorkOrder::query()->lockForUpdate()->findOrFail($wo->id);
            Approval::ensureStatus($wo->status, 'approved', 'Variations need an approved work order.');
            if ($wo->variations()->where('status', 'pending')->exists()) {
                throw new ConflictHttpException('A variation is already waiting for approval.');
            }
            $this->checkVariation($wo, $newAmount);

            return $wo->variations()->create([
                'variation_no' => DocumentNumber::next('WOV'),
                'old_amount' => $wo->contract_amount,
                'new_amount' => Money::amount($newAmount),
                'reason' => $reason,
                'status' => 'pending',
                'requested_by' => $user->id,
            ]);
        });
    }

    public function approveVariation(ContractorWorkOrderVariation $variation, User $user): void
    {
        DB::transaction(function () use ($variation, $user) {
            $variation = ContractorWorkOrderVariation::query()->lockForUpdate()->findOrFail($variation->id);
            Approval::ensureStatus($variation->status, 'pending', 'This variation is no longer pending.');
            Approval::ensureNotSelf($user, $variation->requested_by, 'a variation');
            $wo = ContractorWorkOrder::query()->lockForUpdate()->findOrFail($variation->work_order_id);
            Approval::ensureStatus($wo->status, 'approved', 'The work order is no longer open.');
            if (Money::cmp($wo->contract_amount, $variation->old_amount) !== 0) {
                throw new ConflictHttpException('The contract changed after this request. Request the variation again.');
            }
            $this->checkVariation($wo, $variation->new_amount);

            $variation->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
            $wo->forceFill(['contract_amount' => $variation->new_amount])->save();
        });
    }

    public function rejectVariation(ContractorWorkOrderVariation $variation, string $reason, User $user): void
    {
        DB::transaction(function () use ($variation, $reason, $user) {
            $variation = ContractorWorkOrderVariation::query()->lockForUpdate()->findOrFail($variation->id);
            Approval::ensureStatus($variation->status, 'pending', 'This variation is no longer pending.');
            $variation->forceFill(['status' => 'rejected', 'rejection_reason' => $reason, 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    private function checkVariation(ContractorWorkOrder $wo, string $newAmount): void
    {
        if (! Money::isPositive($newAmount)) {
            throw ValidationException::withMessages(['new_amount' => 'Enter the revised contract amount.']);
        }
        if (Money::cmp($newAmount, $wo->contract_amount) === 0) {
            throw ValidationException::withMessages(['new_amount' => 'The revised amount is the same as the current contract.']);
        }
        if (Money::cmp($newAmount, $wo->measuredAmount()) < 0) {
            throw ValidationException::withMessages(['new_amount' => 'Cannot be below the measured value of '.Money::amount($wo->measuredAmount()).'.']);
        }
    }

    public function recordMeasurement(ContractorWorkOrder $wo, array $data, User $user): ContractorMeasurement
    {
        return DB::transaction(function () use ($wo, $data, $user) {
            $wo = ContractorWorkOrder::query()->lockForUpdate()->findOrFail($wo->id);
            Approval::ensureStatus($wo->status, 'approved', 'Measurements need an approved work order.');

            $amount = Money::mul(Money::qty($data['quantity']), Money::rate($data['rate']));
            if (! Money::isPositive($amount)) {
                throw ValidationException::withMessages(['quantity' => 'Quantity and rate must give an amount above zero.']);
            }
            $remaining = Money::sub($wo->contract_amount, $wo->measuredAmount());
            if (Money::cmp($amount, $remaining) > 0) {
                throw new ConflictHttpException('This exceeds the contract. Remaining value: '.Money::amount($remaining).'. Request a variation to revise the contract.');
            }

            return $wo->measurements()->create([
                'measurement_date' => $data['measurement_date'],
                'description' => $data['description'],
                'quantity' => Money::qty($data['quantity']),
                'unit' => $data['unit'],
                'rate' => Money::rate($data['rate']),
                'measured_amount' => $amount,
                'status' => 'draft',
                'submitted_by' => $user->id,
            ]);
        });
    }

    public function approveMeasurement(ContractorMeasurement $m, User $user): void
    {
        DB::transaction(function () use ($m, $user) {
            $m = ContractorMeasurement::query()->lockForUpdate()->findOrFail($m->id);
            Approval::ensureStatus($m->status, 'draft', 'This measurement is no longer a draft.');
            Approval::ensureNotSelf($user, $m->submitted_by, 'a measurement');
            $m->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    public function rejectMeasurement(ContractorMeasurement $m, string $reason, User $user): void
    {
        DB::transaction(function () use ($m, $reason, $user) {
            $m = ContractorMeasurement::query()->lockForUpdate()->findOrFail($m->id);
            Approval::ensureStatus($m->status, 'draft', 'This measurement is no longer a draft.');
            $m->forceFill(['status' => 'rejected', 'rejection_reason' => $reason, 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    /**
     * @param  array<int, int>  $measurementIds
     * @param  array{advance_adjustment?: string, retention_amount?: ?string, other_deductions?: string, deduction_note?: ?string, bill_date: string, notes?: ?string}  $data
     */
    public function createBill(ContractorWorkOrder $wo, array $measurementIds, array $data, User $user): ContractorBill
    {
        return DB::transaction(function () use ($wo, $measurementIds, $data, $user) {
            $wo = ContractorWorkOrder::query()->lockForUpdate()->findOrFail($wo->id);
            Approval::ensureStatus($wo->status, 'approved', 'Bills need an approved work order.');

            $measurements = ContractorMeasurement::query()->whereIn('id', $measurementIds)->lockForUpdate()->get();
            if ($measurements->isEmpty() || $measurements->count() !== count(array_unique($measurementIds))) {
                throw ValidationException::withMessages(['measurements' => 'Select approved measurements to bill.']);
            }
            foreach ($measurements as $m) {
                if ($m->work_order_id !== $wo->id) {
                    throw ValidationException::withMessages(['measurements' => 'All measurements must belong to this work order.']);
                }
                if ($m->status === 'billed') {
                    throw new ConflictHttpException("The measurement \"{$m->description}\" is already billed.");
                }
                if ($m->status !== 'approved') {
                    throw ValidationException::withMessages(['measurements' => 'Only approved measurements can be billed.']);
                }
            }

            $gross = Money::amount(Money::add('0', ...$measurements->map(fn ($m) => (string) $m->measured_amount)->all()));
            $retention = isset($data['retention_amount']) && $data['retention_amount'] !== null && $data['retention_amount'] !== ''
                ? Money::amount($data['retention_amount'])
                : Money::amount(Money::div(Money::mul($gross, $wo->retention_percent), '100'));
            $advance = Money::amount($data['advance_adjustment'] ?? '0');
            $other = Money::amount($data['other_deductions'] ?? '0');

            $this->checkDeductions($wo, $gross, $advance, $retention, $other, null);

            $bill = ContractorBill::query()->create([
                'bill_no' => DocumentNumber::next('CB'),
                'kind' => 'running',
                'work_order_id' => $wo->id,
                'contractor_id' => $wo->contractor_id,
                'project_id' => $wo->project_id,
                'bill_date' => $data['bill_date'],
                'gross_amount' => $gross,
                'advance_adjustment' => $advance,
                'retention_amount' => $retention,
                'other_deductions' => $other,
                'deduction_note' => $data['deduction_note'] ?? null,
                'net_payable' => Money::amount(Money::sub(Money::sub(Money::sub($gross, $advance), $retention), $other)),
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => $user->id,
            ]);
            foreach ($measurements as $m) {
                $bill->measurementLinks()->create(['measurement_id' => $m->id, 'amount' => $m->measured_amount]);
                $m->forceFill(['status' => 'billed'])->save();
            }

            return $bill;
        });
    }

    public function approveBill(ContractorBill $bill, User $user): void
    {
        DB::transaction(function () use ($bill, $user) {
            $bill = ContractorBill::query()->with(['contractor', 'project', 'workOrder'])->lockForUpdate()->findOrFail($bill->id);
            Approval::ensureStatus($bill->status, 'draft', 'This bill is no longer a draft.');
            Approval::ensureNotSelf($user, $bill->created_by, 'a contractor bill');
            $wo = ContractorWorkOrder::query()->lockForUpdate()->findOrFail($bill->work_order_id);
            $this->checkDeductions($wo, $bill->gross_amount, $bill->advance_adjustment, $bill->retention_amount, $bill->other_deductions, $bill->id);

            $date = $bill->bill_date;
            $bill->forceFill(['status' => Money::isPositive($bill->net_payable) ? 'approved' : 'paid', 'approved_by' => $user->id, 'posted_at' => now()])->save();

            $release = $bill->kind === 'retention_release';
            $this->ledger->post($bill->contractor, $release ? 'retention_release' : 'bill', $bill, $date,
                ($release ? 'Retention release ' : 'Bill ').$bill->bill_no.' · '.$wo->wo_no, $bill->chargeAmount(), '0', $user);
            if ($release) {
                $wo->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
            }
            if (Money::isPositive($bill->costAmount())) {
                $this->costs->post($bill->project, $bill, 'contractor', $bill->costAmount(), $date, $wo->work_package_id);
            }
            $this->events->record('contractors', $bill, $release ? 'contractor.retention_released' : 'contractor.bill_approved', $release ? $bill->gross_amount : $bill->costAmount(), $date,
                ['bill_no' => $bill->bill_no, 'wo_no' => $wo->wo_no, 'contractor' => $bill->contractor->contractor_code, 'gross' => $bill->gross_amount,
                    'advance_adjustment' => $bill->advance_adjustment, 'retention' => $bill->retention_amount, 'other_deductions' => $bill->other_deductions, 'net_payable' => $bill->net_payable],
                project: $bill->project, user: $user);
        });
    }

    public function cancelBill(ContractorBill $bill, User $user): void
    {
        DB::transaction(function () use ($bill) {
            $bill = ContractorBill::query()->lockForUpdate()->findOrFail($bill->id);
            Approval::ensureStatus($bill->status, 'draft', 'Only draft bills can be discarded.');
            $this->releaseMeasurements($bill);
            $bill->forceFill(['status' => 'cancelled'])->save();
        });
    }

    public function reverseBill(ContractorBill $bill, string $reason, User $user): void
    {
        DB::transaction(function () use ($bill, $reason, $user) {
            $bill = ContractorBill::query()->lockForUpdate()->findOrFail($bill->id);
            Approval::ensureStatus($bill->status, ['approved', 'paid'], 'Only approved bills can be reversed.');
            if ($bill->payments()->whereIn('status', ['pending', 'approved'])->exists()) {
                throw new ConflictHttpException('Reverse the payments made against this bill first.');
            }
            if ($bill->kind === 'running' && Money::isPositive($bill->retention_amount)) {
                $wo = ContractorWorkOrder::query()->lockForUpdate()->findOrFail($bill->work_order_id);
                if (Money::cmp(Money::sub($wo->retentionHeld(), $bill->retention_amount), '0') < 0) {
                    throw new ConflictHttpException('Retention from this bill was already released. Reverse the release first.');
                }
            }

            $this->releaseMeasurements($bill);
            $bill->forceFill(['status' => 'reversed', 'reversed_by' => $user->id, 'reversed_at' => now(), 'reversal_reason' => $reason])->save();
            $this->ledger->reverse($bill, $bill->kind === 'retention_release' ? 'retention_release' : 'bill', $reason, $user);
            $this->costs->reverse($bill, now(config('app.display_timezone')));
            $this->events->reverse('contractors', $bill, now(config('app.display_timezone')), $user);
        });
    }

    /**
     * Final settlement: bills the retention still held as a release bill. The work order completes when it is approved.
     */
    public function settle(ContractorWorkOrder $wo, string $billDate, User $user): ?ContractorBill
    {
        return DB::transaction(function () use ($wo, $billDate, $user) {
            $wo = ContractorWorkOrder::query()->lockForUpdate()->findOrFail($wo->id);
            Approval::ensureStatus($wo->status, 'approved', 'Only approved work orders can be settled.');
            if ($wo->measurements()->whereIn('status', ['draft', 'approved'])->exists() || $wo->bills()->where('status', 'draft')->exists()) {
                throw new ConflictHttpException('Bill or close all measurements and draft bills first.');
            }

            $held = $wo->retentionHeld();
            $advance = $wo->availableAdvance();
            if (Money::cmp($advance, $held) > 0) {
                throw new ConflictHttpException('The contractor still holds '.Money::amount(Money::sub($advance, $held)).' of unused advance. Record its refund before settling.');
            }
            if (! Money::isPositive($held)) {
                $wo->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

                return null;
            }

            // Whatever advance is left is recovered from the retention being released.
            return ContractorBill::query()->create([
                'bill_no' => DocumentNumber::next('CB'),
                'kind' => 'retention_release',
                'work_order_id' => $wo->id,
                'contractor_id' => $wo->contractor_id,
                'project_id' => $wo->project_id,
                'bill_date' => $billDate,
                'gross_amount' => $held,
                'advance_adjustment' => $advance,
                'net_payable' => Money::amount(Money::sub($held, $advance)),
                'status' => 'draft',
                'created_by' => $user->id,
            ]);
        });
    }

    /**
     * A payment with a bill settles that bill; without one it is an advance on the work order.
     */
    public function createPayment(ContractorWorkOrder $wo, ?ContractorBill $bill, string $amount, array $data, User $user, string $key, string $kind = 'payment'): ContractorPayment
    {
        return DB::transaction(function () use ($wo, $bill, $amount, $data, $user, $key, $kind) {
            if ($existing = ContractorPayment::query()->where('idempotency_key', $key)->first()) {
                return $existing;
            }
            $wo = ContractorWorkOrder::query()->lockForUpdate()->findOrFail($wo->id);
            $kind === 'refund' ? $this->checkRefund($wo, $amount, null) : $this->checkPayment($wo, $bill, $amount, null);

            return ContractorPayment::query()->create([
                'payment_no' => DocumentNumber::next('CP'),
                'contractor_id' => $wo->contractor_id,
                'work_order_id' => $wo->id,
                'bill_id' => $kind === 'refund' ? null : $bill?->id,
                'kind' => $kind,
                'project_id' => $wo->project_id,
                'payment_date' => $data['payment_date'],
                'amount' => Money::amount($amount),
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'created_by' => $user->id,
                'idempotency_key' => $key,
            ]);
        });
    }

    public function approvePayment(ContractorPayment $payment, User $user): void
    {
        DB::transaction(function () use ($payment, $user) {
            $payment = ContractorPayment::query()->with(['contractor', 'project'])->lockForUpdate()->findOrFail($payment->id);
            Approval::ensureStatus($payment->status, 'pending', 'This payment is no longer pending.');
            Approval::ensureNotSelf($user, $payment->created_by, 'a payment');
            $wo = ContractorWorkOrder::query()->lockForUpdate()->findOrFail($payment->work_order_id);
            $bill = $payment->bill_id ? ContractorBill::query()->lockForUpdate()->findOrFail($payment->bill_id) : null;
            if ($payment->isRefund()) {
                $this->checkRefund($wo, $payment->amount, $payment->id);
                $payment->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'posted_at' => now()])->save();
                $this->ledger->post($payment->contractor, 'advance_refund', $payment, $payment->payment_date,
                    'Advance refund '.$payment->payment_no.' · '.$wo->wo_no.' ('.ContractorPayment::METHODS[$payment->payment_method].')', $payment->amount, '0', $user);
                $this->events->record('contractors', $payment, 'contractor.advance_refunded', $payment->amount, $payment->payment_date,
                    ['payment_no' => $payment->payment_no, 'wo_no' => $wo->wo_no, 'method' => $payment->payment_method], project: $payment->project, user: $user);

                return;
            }
            $this->checkPayment($wo, $bill, $payment->amount, $payment->id);

            $payment->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'posted_at' => now()])->save();
            $label = ($bill ? 'Payment '.$payment->payment_no.' · '.$bill->bill_no : 'Advance '.$payment->payment_no.' · '.$wo->wo_no).' ('.ContractorPayment::METHODS[$payment->payment_method].')';
            $this->ledger->post($payment->contractor, $bill ? 'payment' : 'advance', $payment, $payment->payment_date, $label, '0', $payment->amount, $user);
            if ($bill && ! Money::isPositive($bill->outstanding())) {
                $bill->forceFill(['status' => 'paid'])->save();
            }

            $this->events->record('contractors', $payment, 'contractor.payment', $payment->amount, $payment->payment_date,
                ['payment_no' => $payment->payment_no, 'wo_no' => $wo->wo_no, 'bill_no' => $bill?->bill_no, 'advance' => ! $bill, 'method' => $payment->payment_method],
                project: $payment->project, user: $user);
        });
    }

    public function rejectPayment(ContractorPayment $payment, string $reason, User $user): void
    {
        DB::transaction(function () use ($payment, $reason, $user) {
            $payment = ContractorPayment::query()->lockForUpdate()->findOrFail($payment->id);
            Approval::ensureStatus($payment->status, 'pending', 'This payment is no longer pending.');
            $payment->forceFill(['status' => 'rejected', 'reversal_reason' => $reason, 'approved_by' => $user->id])->save();
        });
    }

    public function reversePayment(ContractorPayment $payment, string $reason, User $user): void
    {
        DB::transaction(function () use ($payment, $reason, $user) {
            $payment = ContractorPayment::query()->lockForUpdate()->findOrFail($payment->id);
            Approval::ensureStatus($payment->status, 'approved', 'Only approved payments can be reversed.');
            if ($payment->isAdvance()) {
                $wo = ContractorWorkOrder::query()->lockForUpdate()->findOrFail($payment->work_order_id);
                if (Money::cmp($wo->availableAdvance(), $payment->amount) < 0) {
                    throw new ConflictHttpException('This advance is already adjusted against a bill. Reverse that bill first.');
                }
            }

            $payment->forceFill(['status' => 'reversed', 'reversed_by' => $user->id, 'reversed_at' => now(), 'reversal_reason' => $reason])->save();
            $this->ledger->reverse($payment, match (true) {
                $payment->isRefund() => 'advance_refund', $payment->isAdvance() => 'advance', default => 'payment'
            }, $reason, $user);
            if ($payment->bill_id) {
                ContractorBill::query()->whereKey($payment->bill_id)->where('status', 'paid')->update(['status' => 'approved']);
            }
            $this->events->reverse('contractors', $payment, now(config('app.display_timezone')), $user);
        });
    }

    private function checkDeductions(ContractorWorkOrder $wo, string $gross, string $advance, string $retention, string $other, ?int $billId): void
    {
        foreach (['advance_adjustment' => $advance, 'retention_amount' => $retention, 'other_deductions' => $other] as $field => $value) {
            if (Money::cmp($value, '0') < 0) {
                throw ValidationException::withMessages([$field => 'Cannot be negative.']);
            }
        }
        if (Money::cmp($advance, $wo->availableAdvance($billId)) > 0) {
            throw ValidationException::withMessages(['advance_adjustment' => 'Only '.$wo->availableAdvance($billId).' of advance is available.']);
        }
        if (Money::cmp(Money::add(Money::add($advance, $retention), $other), $gross) > 0) {
            throw ValidationException::withMessages(['other_deductions' => 'Deductions cannot exceed the gross amount.']);
        }
    }

    private function checkPayment(ContractorWorkOrder $wo, ?ContractorBill $bill, string $amount, ?int $ignorePaymentId): void
    {
        if (! Money::isPositive($amount)) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }
        if ($bill === null) {
            Approval::ensureStatus($wo->status, 'approved', 'Advances need an approved work order.');

            return;
        }
        if ($bill->work_order_id !== $wo->id) {
            throw ValidationException::withMessages(['bill' => 'Select a bill of this work order.']);
        }
        Approval::ensureStatus($bill->status, 'approved', 'Only approved, unpaid bills can be paid.');
        $reserved = (string) $bill->payments()->whereIn('status', ['pending', 'approved'])
            ->when($ignorePaymentId, fn ($q) => $q->where('id', '!=', $ignorePaymentId))->sum('amount');
        $due = Money::sub($bill->net_payable, $reserved);
        if (Money::cmp($amount, $due) > 0) {
            throw ValidationException::withMessages(['amount' => 'Cannot pay more than the outstanding '.Money::amount($due).'.']);
        }
    }

    /** A refund pays back advance that no bill has used yet. */
    private function checkRefund(ContractorWorkOrder $wo, string $amount, ?int $ignoreRefundId): void
    {
        if (! Money::isPositive($amount)) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }
        $available = $wo->availableAdvance(null, $ignoreRefundId);
        if (Money::cmp($amount, $available) > 0) {
            throw ValidationException::withMessages(['amount' => 'Only '.Money::amount($available).' of advance is unused.']);
        }
    }

    private function releaseMeasurements(ContractorBill $bill): void
    {
        $ids = $bill->measurementLinks()->pluck('measurement_id');
        $bill->measurementLinks()->delete();
        ContractorMeasurement::query()->whereIn('id', $ids)->update(['status' => 'approved']);
    }
}
