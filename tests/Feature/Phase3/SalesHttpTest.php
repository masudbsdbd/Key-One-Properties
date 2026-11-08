<?php

namespace Tests\Feature\Phase3;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\FlatSale;
use App\Models\Project;
use App\Models\SalesCancellation;
use App\Models\User;
use Database\Factories\FlatFactory;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesHttpTest extends TestCase
{
    private const AGENT = ['projects.view', 'flats.view', 'customers.view_all', 'sales.booking.view', 'sales.booking.create', 'sales.booking.edit', 'sales.booking.submit',
        'sales.booking.cancel.request', 'sales.sale.view', 'sales.sale.create', 'sales.refund.view', 'sales.refund.request', 'sales.price.view', 'sales.export'];

    private const MANAGER = ['projects.view', 'flats.view', 'customers.view_all', 'sales.booking.view', 'sales.booking.approve', 'sales.booking.cancel.approve', 'sales.sale.view',
        'sales.sale.approve', 'sales.sale.agreement.manage', 'sales.sale.registration.manage', 'sales.sale.handover.manage', 'sales.refund.view', 'sales.refund.approve',
        'sales.refund.pay', 'sales.price.view', 'sales.price.change.approve'];

    private Project $project;

    private Flat $flat;

    private Customer $customer;

    private User $agent;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create();
        $this->flat = Flat::factory()->forFloor(FlatFactory::floorFor($this->project))->create(['list_price' => 6000000]);
        $this->customer = Customer::factory()->create();
        $this->agent = $this->userWith(self::AGENT);
        $this->manager = $this->userWith(self::MANAGER);
        $this->project->members()->attach([$this->agent->id, $this->manager->id]);
    }

    public function test_booking_to_sale_through_the_screens(): void
    {
        $options = $this->actingAs($this->agent)->ajax()->get(route('options.sale-flats'))->assertOk()->json('results');
        $this->assertSame($this->flat->public_id, $options[0]['id']);
        $this->actingAs($this->agent)->ajax()->get(route('sales.flat-summary', ['flat' => $this->flat->public_id]))->assertOk()->assertJsonPath('success', true);

        $this->actingAs($this->agent)->ajax()->post(route('bookings.store'), [
            'flat' => $this->flat->public_id, 'customer' => $this->customer->public_id, 'booking_date' => now()->toDateString(),
            'agreed_price' => '5,800,000', 'booking_amount_expected' => '6,000,001', 'submit' => '1',
        ])->assertStatus(422)->assertJsonValidationErrors('booking_amount_expected');
        $this->actingAs($this->agent)->ajax()->post(route('bookings.store'), [
            'flat' => $this->flat->public_id, 'customer' => $this->customer->public_id, 'booking_date' => now()->toDateString(),
            'agreed_price' => '5,800,000', 'booking_amount_expected' => '500,000', 'submit' => '1',
        ])->assertOk();
        $booking = FlatBooking::query()->firstOrFail();
        $this->assertSame('pending', $booking->status);

        $this->actingAs($this->agent)->ajax()->post(route('bookings.approve', $booking))->assertForbidden();
        $this->actingAs($this->manager)->ajax()->post(route('bookings.approve', $booking))->assertOk();
        $this->assertSame('booked', $this->flat->fresh()->status);
        $this->assertSame([], $this->actingAs($this->agent)->ajax()->get(route('options.sale-flats'))->json('results'));

        $this->actingAs($this->agent)->ajax()->get(route('sales.create', ['booking' => $booking->public_id]))->assertOk()->assertSee($booking->booking_no);
        $this->actingAs($this->agent)->ajax()->post(route('sales.store'), [
            'booking' => $booking->public_id, 'agreement_date' => now()->toDateString(), 'sale_price' => '5800000', 'discount_amount' => '50000', 'submit' => '1',
        ])->assertOk();
        $sale = FlatSale::query()->firstOrFail();
        $this->actingAs($this->manager)->ajax()->post(route('sales.approve', $sale))->assertOk();
        $this->assertSame('sold', $this->flat->fresh()->status);

        $this->actingAs($this->manager)->ajax()->post(route('sales.stage.update', [$sale, 'registration']), ['status' => 'registered'])
            ->assertStatus(422)->assertJsonValidationErrors('registration_no');
        $this->actingAs($this->manager)->ajax()->post(route('sales.stage.update', [$sale, 'registration']), ['status' => 'registered', 'registration_no' => 'R-77', 'registered_at' => now()->toDateString()])->assertOk();
        $this->assertSame('registered', $sale->fresh()->status);

        foreach ([route('sales.overview'), route('sales.availability'), route('bookings.index'), route('bookings.show', $booking), route('sales.index'), route('sales.show', $sale),
            route('cancellations.index'), route('refunds.index'), route('price-revisions.index'), route('sales.document', $sale)] as $url) {
            $this->actingAs($this->manager)->get($url)->assertOk();
        }
        $this->actingAs($this->manager)->ajax()->get(route('sales.board', ['project' => $this->project->public_id]))->assertOk()->assertSee($this->flat->flat_no);
        foreach (['bookings.table', 'sales.table', 'cancellations.table', 'refunds.table', 'price-revisions.table'] as $name) {
            $this->actingAs($this->manager)->ajax()->get(route($name))->assertOk();
        }
        $this->actingAs($this->agent)->get(route('bookings.export', ['format' => 'pdf']))->assertOk();
    }

    public function test_cancellation_and_refund_through_the_screens(): void
    {
        $booking = FlatBooking::query()->create(['booking_no' => 'BK-T-9', 'project_id' => $this->project->id, 'flat_id' => $this->flat->id, 'customer_id' => $this->customer->id,
            'booking_date' => now(), 'agreed_price' => '100', 'status' => 'pending', 'created_by' => $this->agent->id]);
        $this->actingAs($this->manager)->ajax()->post(route('bookings.approve', $booking))->assertOk();
        CustomerPayment::query()->create(['payment_no' => 'CP-T-1', 'customer_id' => $this->customer->id, 'project_id' => $this->project->id, 'purpose' => 'booking', 'booking_id' => $booking->id,
            'payment_date' => now(), 'amount' => '80', 'method' => 'cash', 'status' => 'posted', 'received_by' => $this->agent->id, 'posted_at' => now(), 'idempotency_key' => (string) Str::uuid()]);

        $this->actingAs($this->agent)->ajax()->post(route('bookings.cancel', $booking), ['reason' => 'Changed mind', 'refund_expected' => '81'])
            ->assertStatus(422)->assertJsonValidationErrors('refund_expected');
        $this->actingAs($this->agent)->ajax()->post(route('bookings.cancel', $booking), ['reason' => 'Changed mind', 'refund_expected' => '60'])->assertOk();
        $cancellation = SalesCancellation::query()->firstOrFail();
        $this->actingAs($this->agent)->ajax()->post(route('cancellations.approve', $cancellation))->assertForbidden();
        $this->actingAs($this->manager)->ajax()->post(route('cancellations.approve', $cancellation))->assertOk();
        $this->assertSame('available', $this->flat->fresh()->status);

        $refund = CustomerRefund::query()->firstOrFail();
        $this->assertSame('pending', $refund->status);
        $this->actingAs($this->manager)->ajax()->post(route('refunds.approve', $refund))->assertOk();
        $this->actingAs($this->manager)->ajax()->post(route('refunds.pay', $refund), ['paid_on' => now()->toDateString(), 'payment_method' => 'cash'])->assertOk();
        $this->assertSame('paid', $refund->fresh()->status);
        $this->actingAs($this->manager)->get(route('refunds.show', $refund))->assertOk();
        $this->assertStringStartsWith('%PDF', $this->actingAs($this->manager)->get(route('refunds.document', $refund).'?format=pdf')->getContent());
    }

    public function test_out_of_project_users_and_hidden_customers_are_refused(): void
    {
        $booking = FlatBooking::query()->create(['booking_no' => 'BK-T-8', 'project_id' => $this->project->id, 'flat_id' => $this->flat->id, 'customer_id' => $this->customer->id,
            'booking_date' => now(), 'agreed_price' => '100', 'status' => 'pending', 'created_by' => $this->agent->id]);
        $outsider = $this->userWith(array_merge(self::AGENT, self::MANAGER));

        $this->actingAs($outsider)->get(route('bookings.show', $booking))->assertForbidden();
        $this->actingAs($outsider)->ajax()->post(route('bookings.approve', $booking))->assertForbidden();
        $this->assertSame([], $this->actingAs($outsider)->ajax()->get(route('options.sale-flats'))->json('results'));
        $this->assertStringNotContainsString('BK-T-8', $this->actingAs($outsider)->ajax()->get(route('bookings.table'))->json('data.html'));

        $ownOnly = $this->userWith(['projects.view', 'customers.view', 'sales.booking.create']);
        $this->project->members()->attach($ownOnly->id);
        $this->actingAs($ownOnly)->ajax()->post(route('bookings.store'), [
            'flat' => $this->flat->public_id, 'customer' => $this->customer->public_id, 'booking_date' => now()->toDateString(), 'agreed_price' => '100',
        ])->assertStatus(422)->assertJsonValidationErrors('customer');
    }
}
