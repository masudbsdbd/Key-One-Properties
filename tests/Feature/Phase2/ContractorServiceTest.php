<?php

namespace Tests\Feature\Phase2;

use App\Models\Contractor;
use App\Models\ContractorBill;
use App\Models\ContractorPayment;
use App\Models\ContractorWorkOrder;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Models\User;
use App\Modules\Contractors\Services\ContractorService;
use App\Support\Reconciliation\SubledgerReconciler;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class ContractorServiceTest extends TestCase
{
    private ContractorService $service;

    private User $engineer;

    private User $manager;

    private Contractor $contractor;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContractorService::class);
        $this->engineer = User::factory()->create();
        $this->manager = User::factory()->create();
        $this->project = Project::factory()->create();
        $this->contractor = $this->service->createContractor(['name' => 'Rahim Construction', 'trade' => 'Civil', 'status' => 'active'], $this->engineer);
    }

    private function workOrder(string $contract = '5000', string $retention = '0'): ContractorWorkOrder
    {
        $wo = $this->service->saveWorkOrder(null, $this->contractor, $this->project, null,
            ['title' => 'Slab casting', 'contract_amount' => $contract, 'retention_percent' => $retention], $this->engineer);
        $this->service->approveWorkOrder($wo, $this->manager);

        return $wo->fresh();
    }

    private function measured(ContractorWorkOrder $wo, string $qty, string $rate): int
    {
        $m = $this->service->recordMeasurement($wo, ['measurement_date' => now()->toDateString(), 'description' => 'Level '.Str::random(3), 'quantity' => $qty, 'unit' => 'sft', 'rate' => $rate], $this->engineer);
        $this->service->approveMeasurement($m, $this->manager);

        return $m->id;
    }

    private function pay(ContractorWorkOrder $wo, ?ContractorBill $bill, string $amount): ContractorPayment
    {
        $p = $this->service->createPayment($wo, $bill, $amount, ['payment_date' => now()->toDateString(), 'payment_method' => 'bank'], $this->engineer, (string) Str::uuid());
        $this->service->approvePayment($p, $this->manager);

        return $p->fresh();
    }

    public function test_blueprint_scenario_advance_retention_and_net_payable(): void
    {
        $wo = $this->workOrder();
        $this->pay($wo, null, '200');
        $this->assertSame('-200.00', $this->contractor->balance());
        $this->assertSame('200.00', $wo->availableAdvance());

        $bill = $this->service->createBill($wo, [$this->measured($wo, '100', '10')],
            ['bill_date' => now()->toDateString(), 'advance_adjustment' => '200', 'retention_amount' => '50'], $this->engineer);
        $this->assertSame('1000.00', $bill->gross_amount);
        $this->assertSame('750.00', $bill->net_payable);

        $this->service->approveBill($bill, $this->manager);
        $this->assertSame('750.00', $this->contractor->balance());
        $this->assertSame('1000.00', (string) ProjectCostAllocation::query()->where('cost_category', 'contractor')->sum('amount'));
        $this->assertSame('0.00', $wo->availableAdvance());

        $this->pay($wo, $bill->fresh(), '300');
        $this->assertSame('450.00', $bill->fresh()->outstanding());
        $this->assertSame('450.00', $this->contractor->balance());

        $this->expectException(ValidationException::class);
        $this->service->createPayment($wo, $bill->fresh(), '450.01', ['payment_date' => now()->toDateString(), 'payment_method' => 'cash'], $this->engineer, (string) Str::uuid());
    }

    public function test_measurement_cannot_be_billed_twice_or_exceed_the_contract(): void
    {
        $wo = $this->workOrder('1000');
        $id = $this->measured($wo, '50', '10');
        $this->service->createBill($wo, [$id], ['bill_date' => now()->toDateString()], $this->engineer);

        try {
            $this->service->createBill($wo, [$id], ['bill_date' => now()->toDateString()], $this->engineer);
            $this->fail('Billed twice.');
        } catch (ConflictHttpException) {
        }

        $this->expectException(ConflictHttpException::class);
        $this->service->recordMeasurement($wo, ['measurement_date' => now()->toDateString(), 'description' => 'Extra', 'quantity' => '51', 'unit' => 'sft', 'rate' => '10'], $this->engineer);
    }

    public function test_retention_is_released_on_settlement_and_reversal_restores_everything(): void
    {
        $wo = $this->workOrder('10000', '5');
        $bill = $this->service->createBill($wo, [$this->measured($wo, '1000', '10')], ['bill_date' => now()->toDateString()], $this->engineer);
        $this->assertSame('500.00', $bill->retention_amount);
        $this->service->approveBill($bill, $this->manager);
        $this->assertSame('9500.00', $this->contractor->balance());
        $this->assertSame('500.00', $wo->retentionHeld());

        $release = $this->service->settle($wo, now()->toDateString(), $this->engineer);
        $this->service->approveBill($release, $this->manager);
        $this->assertSame('10000.00', $this->contractor->balance());
        $this->assertSame('completed', $wo->fresh()->status);
        $this->assertSame('10000.00', (string) ProjectCostAllocation::query()->where('cost_category', 'contractor')->sum('amount'));

        try {
            $this->service->reverseBill($bill->fresh(), 'Wrong', $this->manager);
            $this->fail('Bill reversed while its retention was released.');
        } catch (ConflictHttpException) {
        }

        $this->service->reverseBill($release->fresh(), 'Undo', $this->manager);
        $this->service->reverseBill($bill->fresh(), 'Wrong', $this->manager);
        $this->assertSame('0.00', $this->contractor->balance());
        $this->assertSame('0.00', (string) ProjectCostAllocation::query()->where('cost_category', 'contractor')->sum('amount'));
        $this->assertSame('approved', $wo->measurements()->first()->status);
    }

    public function test_self_approval_is_blocked_and_payments_are_idempotent(): void
    {
        $wo = $this->service->saveWorkOrder(null, $this->contractor, $this->project, null, ['title' => 'X', 'contract_amount' => '100'], $this->engineer);
        try {
            $this->service->approveWorkOrder($wo, $this->engineer);
            $this->fail('Self-approved.');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(AssertionFailedError::class, $e);
        }
        $this->service->approveWorkOrder($wo, $this->manager);

        $key = (string) Str::uuid();
        $data = ['payment_date' => now()->toDateString(), 'payment_method' => 'cash'];
        $a = $this->service->createPayment($wo->fresh(), null, '50', $data, $this->engineer, $key);
        $b = $this->service->createPayment($wo->fresh(), null, '50', $data, $this->engineer, $key);
        $this->assertTrue($a->is($b));

        $this->service->approvePayment($a, $this->manager);
        $this->service->reversePayment($a->fresh(), 'Duplicate', $this->manager);
        $this->assertSame('0.00', $this->contractor->balance());
    }

    public function test_variation_revises_the_contract_after_approval_and_never_below_measured(): void
    {
        $wo = $this->workOrder('1000');
        $this->measured($wo, '80', '10');

        try {
            $this->service->requestVariation($wo, '700', 'Scope cut', $this->engineer);
            $this->fail('Variation below measured value.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('new_amount', $e->errors());
        }

        $variation = $this->service->requestVariation($wo, '1500', 'Extra floor', $this->engineer);
        $this->expectExceptionOnDuplicate(fn () => $this->service->requestVariation($wo, '1600', 'Again', $this->engineer));
        $this->assertSame('1000.00', $wo->fresh()->contract_amount);

        try {
            $this->service->approveVariation($variation, $this->engineer);
            $this->fail('Self-approved variation.');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(AssertionFailedError::class, $e);
        }
        $this->service->approveVariation($variation, $this->manager);
        $this->assertSame('1500.00', $wo->fresh()->contract_amount);

        $this->measured($wo->fresh(), '60', '10');
        $this->assertSame('1400.00', $wo->fresh()->measuredAmount());
    }

    private function expectExceptionOnDuplicate(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Second pending variation accepted.');
        } catch (ConflictHttpException) {
        }
    }

    public function test_settlement_recovers_leftover_advance_from_retention(): void
    {
        $wo = $this->workOrder('1000', '10');
        $this->pay($wo, null, '150');
        $bill = $this->service->createBill($wo, [$this->measured($wo, '100', '10')], ['bill_date' => now()->toDateString(), 'advance_adjustment' => '100'], $this->engineer);
        $this->service->approveBill($bill, $this->manager);
        $this->pay($wo, $bill->fresh(), $bill->fresh()->net_payable);
        $this->assertSame('50.00', $wo->fresh()->availableAdvance());

        // Retention 100 is held; the 50 of unused advance is taken out of it.
        $release = $this->service->settle($wo->fresh(), now()->toDateString(), $this->engineer);
        $this->assertSame(['100.00', '50.00', '50.00'], [$release->gross_amount, $release->advance_adjustment, $release->net_payable]);
        $this->service->approveBill($release, $this->manager);
        $this->assertSame(['completed', '0.00', '50.00'], [$wo->fresh()->status, $wo->fresh()->availableAdvance(), $this->contractor->balance()]);
        $this->pay($wo, $release->fresh(), '50');
        $this->assertSame('0.00', $this->contractor->balance());
        $this->assertSame('1000.00', (string) ProjectCostAllocation::query()->where('cost_category', 'contractor')->sum('amount'));
        $this->assertSame([], app(SubledgerReconciler::class)->differences());
    }

    public function test_advance_beyond_the_retention_is_refunded_before_settling(): void
    {
        $wo = $this->workOrder('1000', '10');
        $this->pay($wo, null, '300');
        $bill = $this->service->createBill($wo, [$this->measured($wo, '100', '10')], ['bill_date' => now()->toDateString(), 'advance_adjustment' => '100'], $this->engineer);
        $this->service->approveBill($bill, $this->manager);
        $this->pay($wo, $bill->fresh(), $bill->fresh()->net_payable);

        try {
            $this->service->settle($wo->fresh(), now()->toDateString(), $this->engineer);
            $this->fail('Settling with 200 of advance against 100 of retention must be refused.');
        } catch (ConflictHttpException $e) {
            $this->assertStringContainsString('100.00 of unused advance', $e->getMessage());
        }

        $refund = $this->service->createPayment($wo->fresh(), null, '100', ['payment_date' => now()->toDateString(), 'payment_method' => 'bank'], $this->engineer, (string) Str::uuid(), 'refund');
        $this->assertRefundIsCappedAtUnusedAdvance($wo);
        $this->service->approvePayment($refund, $this->manager);
        $this->assertSame(['100.00', '-100.00'], [$wo->fresh()->availableAdvance(), $this->contractor->balance()]);

        $release = $this->service->settle($wo->fresh(), now()->toDateString(), $this->engineer);
        $this->assertSame('0.00', $release->net_payable);
        $this->service->approveBill($release, $this->manager);
        $this->assertSame(['completed', 'paid', '0.00'], [$wo->fresh()->status, $release->fresh()->status, $this->contractor->balance()]);
        $this->assertSame([], app(SubledgerReconciler::class)->differences());

        $this->service->reversePayment($refund->fresh(), 'Cheque bounced', $this->manager);
        $this->assertSame('-100.00', $this->contractor->balance());
    }

    private function assertRefundIsCappedAtUnusedAdvance(ContractorWorkOrder $wo): void
    {
        try {
            $this->service->createPayment($wo->fresh(), null, '150', ['payment_date' => now()->toDateString(), 'payment_method' => 'bank'], $this->engineer, (string) Str::uuid(), 'refund');
            $this->fail('A refund above the unused advance (minus pending refunds) must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame('Only 100.00 of advance is unused.', $e->errors()['amount'][0]);
        }
    }
}
