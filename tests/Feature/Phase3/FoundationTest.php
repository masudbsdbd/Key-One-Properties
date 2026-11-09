<?php

namespace Tests\Feature\Phase3;

use App\Models\Customer;
use App\Models\CustomerStatementEntry;
use App\Models\Flat;
use App\Models\FlatBooking;
use App\Models\Investor;
use App\Models\Project;
use App\Models\User;
use App\Modules\Collections\Services\CustomerStatement;
use App\Modules\Investors\Services\InvestorLedger;
use App\Modules\Sales\Services\FlatClaims;
use Database\Factories\FlatFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_a_flat_can_have_only_one_live_claim(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $flat = Flat::factory()->forFloor(FlatFactory::floorFor($project))->create();
        $customer = Customer::factory()->create();
        $a = FlatBooking::query()->create(['booking_no' => 'BK-T-1', 'project_id' => $project->id, 'flat_id' => $flat->id, 'customer_id' => $customer->id,
            'booking_date' => now(), 'agreed_price' => '100', 'status' => 'pending', 'created_by' => $user->id]);
        $b = $a->replicate(['public_id'])->fill(['booking_no' => 'BK-T-2']);
        $b->save();

        $claims = app(FlatClaims::class);
        DB::transaction(fn () => $claims->acquire($flat, $a, $user));
        DB::transaction(fn () => $claims->acquire($flat, $a, $user));

        $this->expectException(ConflictHttpException::class);
        DB::transaction(fn () => $claims->acquire($flat->fresh(), $b, $user));
    }

    public function test_database_rejects_a_second_claim_row(): void
    {
        $user = User::factory()->create();
        $flat = Flat::factory()->forFloor(FlatFactory::floorFor())->create();
        DB::table('active_flat_claims')->insert(['flat_id' => $flat->id, 'claimant_type' => 'x', 'claimant_id' => 1, 'claimed_by' => $user->id, 'claimed_at' => now()]);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('active_flat_claims')->insert(['flat_id' => $flat->id, 'claimant_type' => 'y', 'claimant_id' => 2, 'claimed_by' => $user->id, 'claimed_at' => now()]);
    }

    public function test_statement_and_investor_ledger_are_append_only_and_balance(): void
    {
        $customer = Customer::factory()->create();
        $statement = app(CustomerStatement::class);
        $entry = $statement->post($customer, 'sale', $customer, now(), 'Contract', '1000', '0');
        $statement->post($customer, 'payment', $entry, now(), 'Paid', '0', '400');
        $this->assertSame('600.00', CustomerStatement::balance($customer));
        $statement->reverse($entry, 'payment', 'Bounced cheque');
        $this->assertSame('1000.00', CustomerStatement::balance($customer));

        try {
            $entry->update(['description' => 'x']);
            $this->fail('Statement row edited.');
        } catch (LogicException) {
        }

        $investor = Investor::query()->forceCreate(['public_id' => 'invtestinvtest01', 'investor_code' => 'INV-T', 'name' => 'A', 'status' => 'active', 'created_by' => User::factory()->create()->id]);
        $ledger = app(InvestorLedger::class);
        $ledger->post($investor, 'capital_in', $investor, now(), '5000', 'Capital');
        $ledger->post($investor, 'profit_payable', $entry, now(), '800', 'Profit');
        $ledger->post($investor, 'profit_paid', $customer, now(), '300', 'Paid');
        $ledger->reverse($customer, 'profit_paid', 'Wrong account');
        $this->assertSame(['capital' => '5000.00', 'payable' => '800.00', 'paid' => '0.00', 'profit_due' => '800.00'], InvestorLedger::balances($investor));
        $this->assertSame(2, CustomerStatementEntry::query()->where('entry_type', '!=', 'reversal')->count());
    }
}
