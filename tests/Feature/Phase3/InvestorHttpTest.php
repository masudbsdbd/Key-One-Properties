<?php

namespace Tests\Feature\Phase3;

use App\Models\Flat;
use App\Models\Investor;
use App\Models\InvestorCapitalTransaction;
use App\Models\InvestorFlatAgreement;
use App\Models\Project;
use App\Models\User;
use Database\Factories\FlatFactory;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvestorHttpTest extends TestCase
{
    private const OFFICER = ['projects.view', 'investor.view', 'investor.create', 'investor.edit', 'investor.ledger.view', 'investor.ledger.export', 'investor.agreement.create', 'investor.agreement.edit',
        'investor.capital.record', 'investor.capital.return'];

    private const MANAGER = ['projects.view', 'investor.view', 'investor.ledger.view', 'investor.agreement.approve', 'investor.agreement.close', 'investor.capital.approve'];

    private Project $project;

    private Flat $flat;

    private User $officer;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create();
        $this->flat = Flat::factory()->forFloor(FlatFactory::floorFor($this->project))->create();
        $this->officer = $this->userWith(self::OFFICER);
        $this->manager = $this->userWith(self::MANAGER);
        $this->project->members()->attach([$this->officer->id, $this->manager->id]);
    }

    private function newInvestor(string $name): Investor
    {
        $this->actingAs($this->officer)->ajax()->post(route('investors.store'), ['name' => $name, 'mobile' => '01711-222333', 'status' => 'active'])->assertOk();

        return Investor::query()->where('name', $name)->firstOrFail();
    }

    private function agreementInput(Investor $investor, string $share): array
    {
        return ['investor' => $investor->public_id, 'flat' => $this->flat->public_id, 'profit_share_percent' => $share, 'committed_capital' => '1,000,000', 'effective_from' => now()->toDateString()];
    }

    public function test_investor_agreement_capital_and_ledger_flow(): void
    {
        $this->actingAs($this->officer)->ajax()->post(route('investors.store'), ['name' => 'Bad', 'mobile' => 'abc', 'status' => 'active'])->assertStatus(422)->assertJsonValidationErrors('mobile');
        $karim = $this->newInvestor('Karim Uddin');
        $this->assertSame('01711222333', $karim->mobile);
        $this->assertStringStartsWith('INV-', $karim->investor_code);
        $this->assertSame($karim->public_id, $this->actingAs($this->officer)->ajax()->get(route('options.investors', ['term' => 'Karim']))->json('results.0.id'));

        $this->actingAs($this->officer)->ajax()->post(route('investor-agreements.store'), $this->agreementInput($karim, '60') + ['submit' => '1'])->assertOk();
        $a = InvestorFlatAgreement::query()->firstOrFail();
        $this->assertSame('pending', $a->status);
        $this->actingAs($this->officer)->ajax()->post(route('investor-agreements.decide', [$a, 'approve']))->assertForbidden();
        $this->actingAs($this->manager)->ajax()->post(route('investor-agreements.decide', [$a, 'approve']))->assertOk();

        $gauge = $this->actingAs($this->officer)->ajax()->get(route('investor-agreements.shares', ['flat' => $this->flat->public_id, 'from' => now()->toDateString()]))->json('data.html');
        $this->assertStringContainsString('data-allocated="60.0000"', $gauge);
        $this->assertStringContainsString('Karim Uddin', $gauge);

        $rahim = $this->newInvestor('Rahim Ali');
        $this->actingAs($this->officer)->ajax()->post(route('investor-agreements.store'), $this->agreementInput($rahim, '40.5'))->assertStatus(422)->assertJsonValidationErrors('profit_share_percent');
        $this->actingAs($this->officer)->ajax()->post(route('investor-agreements.store'), $this->agreementInput($rahim, '40%'))->assertOk();

        $capital = ['agreement' => $a->public_id, 'transaction_type' => 'contribution', 'amount' => '250,000', 'transaction_date' => now()->toDateString(), 'payment_method' => 'bank', 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($this->officer)->ajax()->post(route('investor-capital.store'), $capital)->assertStatus(422)->assertJsonValidationErrors('reference_no');
        $capital['reference_no'] = 'TRX-1';
        $this->actingAs($this->officer)->ajax()->post(route('investor-capital.store'), $capital)->assertOk();
        $this->actingAs($this->officer)->ajax()->post(route('investor-capital.store'), $capital)->assertOk();
        $this->assertSame(1, InvestorCapitalTransaction::query()->count());
        $tx = InvestorCapitalTransaction::query()->firstOrFail();
        $this->actingAs($this->officer)->ajax()->post(route('investor-capital.post', $tx))->assertForbidden();
        $this->actingAs($this->manager)->ajax()->post(route('investor-capital.post', $tx))->assertOk();
        $this->assertStringStartsWith('%PDF', $this->actingAs($this->officer)->get(route('investor-capital.document', $tx).'?format=pdf')->getContent());
        $this->assertStringStartsWith('%PDF', $this->actingAs($this->officer)->get(route('investor-agreements.document', $a).'?format=pdf')->getContent());

        $ledger = $this->actingAs($this->officer)->ajax()->get(route('investors.ledger', $karim))->assertOk()->json('data.html');
        $this->assertStringContainsString('2,50,000.00', $ledger);
        $this->assertStringContainsString('Investor Ledger', $this->actingAs($this->officer)->get(route('investors.ledger.export', $karim).'?format=print')->getContent());
        $this->actingAs($this->officer)->get(route('investors.show', $karim))->assertOk()->assertSee('2,50,000');

        $return = ['agreement' => $a->public_id, 'transaction_type' => 'capital_return', 'amount' => '300000', 'transaction_date' => now()->toDateString(), 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($this->officer)->ajax()->post(route('investor-capital.store'), $return)->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->actingAs($this->officer)->ajax()->post(route('investor-agreements.decide', [$a, 'amend']))->assertOk()->assertJsonPath('data.redirect', fn ($url) => str_contains($url, '/investor-agreements/'));
        $v2 = InvestorFlatAgreement::query()->where('supersedes_id', $a->id)->firstOrFail();
        $this->actingAs($this->officer)->ajax()->get(route('investor-agreements.edit', $v2))->assertOk();
        $this->actingAs($this->manager)->ajax()->get(route('investor-agreements.reason', [$a, 'close']))->assertOk();
        $this->actingAs($this->manager)->ajax()->post(route('investor-agreements.reason.store', [$a, 'close']), ['reason' => 'Exit', 'effective_to' => now()->toDateString()])->assertOk();
        $this->assertSame('closed', $a->fresh()->status);

        foreach ([route('investors.index'), route('investor-agreements.index'), route('investor-capital.index'), route('investor-agreements.show', $a), route('investor-agreements.show', $v2), route('investor-capital.show', $tx)] as $url) {
            $this->actingAs($this->manager)->get($url)->assertOk();
        }
        foreach (['investors.table', 'investor-agreements.table', 'investor-capital.table'] as $name) {
            $this->actingAs($this->manager)->ajax()->get(route($name))->assertOk();
        }
        $this->actingAs($this->officer)->get(route('investor-agreements.export', ['format' => 'csv']))->assertOk();
    }

    public function test_out_of_project_users_see_no_agreements_or_capital(): void
    {
        $karim = $this->newInvestor('Karim Uddin');
        $this->actingAs($this->officer)->ajax()->post(route('investor-agreements.store'), $this->agreementInput($karim, '50') + ['submit' => '1'])->assertOk();
        $a = InvestorFlatAgreement::query()->firstOrFail();
        $this->actingAs($this->manager)->ajax()->post(route('investor-agreements.decide', [$a, 'approve']))->assertOk();
        $this->actingAs($this->officer)->ajax()->post(route('investor-capital.store'), ['agreement' => $a->public_id, 'transaction_type' => 'contribution', 'amount' => '1000', 'transaction_date' => now()->toDateString(), 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid()])->assertOk();
        $tx = InvestorCapitalTransaction::query()->firstOrFail();
        $this->actingAs($this->manager)->ajax()->post(route('investor-capital.post', $tx))->assertOk();

        $outsider = $this->userWith(array_merge(self::OFFICER, self::MANAGER));
        $this->actingAs($outsider)->get(route('investor-agreements.show', $a))->assertForbidden();
        $this->actingAs($outsider)->get(route('investor-capital.show', $tx))->assertForbidden();
        $this->actingAs($outsider)->ajax()->post(route('investor-capital.post', $tx))->assertForbidden();
        $this->actingAs($outsider)->ajax()->post(route('investor-capital.store'), ['agreement' => $a->public_id, 'transaction_type' => 'contribution', 'amount' => '1', 'transaction_date' => now()->toDateString(), 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid()])
            ->assertStatus(422)->assertJsonValidationErrors('agreement');
        $this->actingAs($outsider)->ajax()->post(route('investor-agreements.store'), $this->agreementInput($karim, '10'))->assertStatus(422)->assertJsonValidationErrors('flat');
        $this->assertSame('', (string) $this->actingAs($outsider)->ajax()->get(route('investor-agreements.shares', ['flat' => $this->flat->public_id]))->json('data.html'));
        $this->assertStringNotContainsString($a->agreement_no, $this->actingAs($outsider)->ajax()->get(route('investor-agreements.table'))->json('data.html'));
        $this->assertStringNotContainsString($tx->transaction_no, $this->actingAs($outsider)->ajax()->get(route('investors.ledger', $karim))->json('data.html'));
        $this->actingAs($outsider)->get(route('investors.show', $karim))->assertOk()->assertDontSee($a->agreement_no);

        $viewer = $this->userWith(['investor.view']);
        $this->actingAs($viewer)->ajax()->get(route('investors.ledger', $karim))->assertForbidden();
        $this->actingAs($viewer)->ajax()->post(route('investors.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
    }
}
