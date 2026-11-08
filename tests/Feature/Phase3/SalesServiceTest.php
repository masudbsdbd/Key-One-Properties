<?php

namespace Tests\Feature\Phase3;

use App\Models\ActiveFlatClaim;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\Project;
use App\Models\User;
use App\Modules\Collections\Services\CustomerStatement;
use App\Modules\Sales\Services\PriceRevisionService;
use App\Modules\Sales\Services\RefundService;
use App\Modules\Sales\Services\SalesService;
use Database\Factories\FlatFactory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class SalesServiceTest extends TestCase
{
    private SalesService $sales;

    private User $agent;

    private User $manager;

    private Flat $flat;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sales = app(SalesService::class);
        [$this->agent, $this->manager] = [User::factory()->create(), User::factory()->create()];
        $this->flat = Flat::factory()->forFloor(FlatFactory::floorFor(Project::factory()->create()))->create(['list_price' => 5000000]);
        $this->customer = Customer::factory()->create();
    }

    private function booking(?Customer $customer = null): FlatBooking
    {
        $b = $this->sales->saveBooking(null, $this->flat->fresh(), $customer ?? $this->customer,
            ['booking_date' => now()->toDateString(), 'agreed_price' => '4800000', 'booking_amount_expected' => '500000'], $this->agent);
        $this->sales->submitBooking($b, $this->agent);

        return $b->fresh();
    }

    private function postedPayment(FlatBooking $booking, string $amount): CustomerPayment
    {
        return CustomerPayment::query()->create(['payment_no' => 'CP-T-'.Str::random(5), 'customer_id' => $booking->customer_id, 'project_id' => $booking->project_id,
            'purpose' => 'booking', 'booking_id' => $booking->id, 'payment_date' => now(), 'amount' => $amount, 'method' => 'cash', 'status' => 'posted',
            'received_by' => $this->agent->id, 'posted_at' => now(), 'idempotency_key' => (string) Str::uuid()]);
    }

    public function test_only_one_booking_can_take_a_flat(): void
    {
        $first = $this->booking();
        $second = $this->booking(Customer::factory()->create());

        $this->sales->approveBooking($first, $this->manager);
        $this->assertSame('booked', $this->flat->fresh()->status);

        try {
            $this->sales->approveBooking($second, $this->manager);
            $this->fail('Second booking took the flat.');
        } catch (ConflictHttpException) {
        }
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame(1, ActiveFlatClaim::query()->count());
        $this->assertTrue(ActiveFlatClaim::query()->first()->claimant->is($first));
    }

    public function test_cancellation_keeps_history_releases_flat_and_does_not_pay_refund(): void
    {
        $booking = $this->booking();
        $this->sales->approveBooking($booking, $this->manager);
        $this->postedPayment($booking, '300000');

        $cancellation = $this->sales->requestCancellation($booking->fresh(), null, 'Customer withdrew', '250000', $this->agent);
        $this->assertSame('cancel_requested', $booking->fresh()->status);
        $refund = $this->sales->approveCancellation($cancellation, $this->manager);

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('available', $this->flat->fresh()->status);
        $this->assertSame(0, ActiveFlatClaim::query()->count());
        $this->assertSame('pending', $refund->status);
        $this->assertSame('250000.00', $refund->amount);
        $this->assertSame('50000.00', SalesService::refundable($booking->fresh(), null));

        $refunds = app(RefundService::class);
        $refunds->approve($refund, $this->manager);
        $refunds->pay($refund->fresh(), ['paid_on' => now()->toDateString(), 'payment_method' => 'bank', 'payment_reference' => 'TRX'], $this->manager);
        $this->assertSame('paid', $refund->fresh()->status);
        $this->assertSame('250000.00', CustomerStatement::balance($this->customer));
    }

    public function test_price_revision_needs_approval_and_does_not_touch_booking_snapshot(): void
    {
        $booking = $this->booking();
        $this->sales->approveBooking($booking, $this->manager);

        $revisions = app(PriceRevisionService::class);
        $revision = $revisions->request($this->flat, '5500000', 'Market rate', $this->agent);
        $this->assertSame('5000000.00', $this->flat->fresh()->list_price);
        try {
            $revisions->approve($revision, $this->agent);
            $this->fail('Self-approved price.');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(AssertionFailedError::class, $e);
        }
        $revisions->approve($revision, $this->manager);

        $this->assertSame('5500000.00', $this->flat->fresh()->list_price);
        $this->assertSame($this->manager->id, $this->flat->priceHistory()->latest('id')->first()->approved_by);
        $this->assertSame('5000000.00', $booking->fresh()->asking_price_snapshot);
        $this->assertSame('4800000.00', $booking->fresh()->agreed_price);
    }

    public function test_booking_converts_to_sale_without_a_second_claim_and_refund_is_capped(): void
    {
        $booking = $this->booking();
        $this->sales->approveBooking($booking, $this->manager);
        $this->postedPayment($booking, '500000');

        $sale = $this->sales->saveSale(null, $this->flat, $this->customer, $booking->fresh(),
            ['agreement_date' => now()->toDateString(), 'sale_price' => '4800000', 'discount_amount' => '100000'], $this->agent);
        try {
            $this->sales->saveSale(null, $this->flat, $this->customer, $booking->fresh(), ['agreement_date' => now()->toDateString(), 'sale_price' => '1'], $this->agent);
            $this->fail('Second sale for one booking.');
        } catch (ConflictHttpException) {
        }
        $this->sales->submitSale($sale, $this->agent);
        $this->sales->approveSale($sale, $this->manager);

        $this->assertSame('converted_to_sale', $booking->fresh()->status);
        $this->assertSame('sold', $this->flat->fresh()->status);
        $this->assertSame(1, ActiveFlatClaim::query()->count());
        $this->assertTrue(ActiveFlatClaim::query()->first()->claimant->is($sale));
        $this->assertSame('4700000.00', CustomerStatement::balance($this->customer));
        $this->assertNotNull($sale->fresh()->agreement);

        $refunds = app(RefundService::class);
        $key = (string) Str::uuid();
        $refunds->request(null, $sale->fresh(), '200000', 'Overpaid', $this->agent, $key);
        $refunds->request(null, $sale->fresh(), '200000', 'Overpaid', $this->agent, $key);
        $this->assertSame(1, CustomerRefund::query()->count());
        $this->expectException(ValidationException::class);
        $refunds->request(null, $sale->fresh(), '300001', 'Too much', $this->agent, (string) Str::uuid());
    }

    public function test_registration_and_handover_move_the_sale_forward(): void
    {
        $sale = $this->sales->saveSale(null, $this->flat, $this->customer, null, ['agreement_date' => now()->toDateString(), 'sale_price' => '5000000'], $this->agent);
        $this->sales->submitSale($sale, $this->agent);
        $this->sales->approveSale($sale, $this->manager);

        $this->sales->updateRegistration($sale, ['status' => 'registered', 'registration_no' => 'REG-1', 'registered_at' => now()->toDateString()], $this->agent);
        $this->assertSame('registered', $sale->fresh()->status);

        try {
            $this->sales->updateHandover($sale, ['status' => 'completed', 'completed_at' => now()->toDateString(), 'checklist' => ['keys']], $this->agent);
            $this->fail('Handover without checklist.');
        } catch (ValidationException) {
        }
        $this->sales->updateHandover($sale, ['status' => 'completed', 'completed_at' => now()->toDateString(), 'checklist' => ['keys', 'meters', 'snags', 'documents']], $this->agent);
        $this->assertSame('handed_over', $sale->fresh()->status);
    }
}
