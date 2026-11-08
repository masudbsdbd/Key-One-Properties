<?php

namespace Tests\Feature\Phase3;

use App\Models\FinancialEvent;
use App\Models\Flat;
use App\Models\Investor;
use App\Models\InvestorFlatAgreement;
use App\Models\InvestorLedgerEntry;
use App\Models\Project;
use App\Models\User;
use App\Modules\Investors\Services\InvestorLedger;
use App\Modules\Investors\Services\InvestorService;
use Database\Factories\FlatFactory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class InvestorServiceTest extends TestCase
{
    private InvestorService $service;

    private User $officer;

    private User $manager;

    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvestorService::class);
        [$this->officer, $this->manager] = [User::factory()->create(), User::factory()->create()];
        $this->flat = Flat::factory()->forFloor(FlatFactory::floorFor(Project::factory()->create()))->create();
    }

    private function investor(string $name = 'Investor'): Investor
    {
        return $this->service->saveInvestor(null, ['name' => $name.' '.Str::random(4), 'mobile' => '01711000000', 'status' => 'active'], $this->officer);
    }

    private function agreement(string $share, string $from = '2026-01-01', ?string $to = null, string $capital = '1000000', ?Investor $investor = null, bool $approve = true): InvestorFlatAgreement
    {
        $a = $this->service->saveAgreement(null, $investor ?? $this->investor(), $this->flat, ['committed_capital' => $capital, 'profit_share_percent' => $share, 'effective_from' => $from, 'effective_to' => $to], $this->officer);
        $this->service->submitAgreement($a, $this->officer);
        if ($approve) {
            $this->service->approveAgreement($a, $this->manager);
        }

        return $a->fresh();
    }

    private function fails(callable $fn, string $field): void
    {
        try {
            $fn();
            $this->fail('Expected a validation error on '.$field);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_shares_never_exceed_100_percent_at_any_date(): void
    {
        $this->agreement('60', '2026-01-01', '2026-03-31');
        $this->agreement('40');
        $this->agreement('60', '2026-04-01');
        $this->assertSame('100.0000', InvestorService::shareSummary($this->flat, now()->setDate(2026, 5, 1), null)['allocated']);
        $this->assertSame('100.0000', InvestorService::shareSummary($this->flat, now()->setDate(2026, 1, 1), null)['allocated']);

        $this->fails(fn () => $this->agreement('0.0001', '2026-02-01'), 'profit_share_percent');
        $this->fails(fn () => $this->agreement('101'), 'profit_share_percent');

        // Two pending agreements that each fit alone: the second approval is refused.
        $other = Flat::factory()->forFloor(FlatFactory::floorFor(Project::factory()->create()))->create();
        $this->flat = $other;
        $a = $this->agreement('70', approve: false);
        $b = $this->agreement('30.0001', approve: false);
        $this->service->approveAgreement($a, $this->manager);
        $this->fails(fn () => $this->service->approveAgreement($b, $this->manager), 'profit_share_percent');
        $this->assertSame('pending', $b->fresh()->status);
    }

    public function test_amendment_creates_a_version_and_closes_the_old_one(): void
    {
        $investor = $this->investor();
        $v1 = $this->agreement('50', investor: $investor);
        $this->agreement('50');
        $v2 = $this->service->amend($v1, $this->officer);
        $this->fails(fn () => $this->service->amend($v1, $this->officer), 'agreement');
        $this->assertSame(2, $v2->version);
        $this->service->saveAgreement($v2, $investor, $this->flat, ['committed_capital' => '1500000', 'profit_share_percent' => '50', 'effective_from' => now()->toDateString()], $this->officer);
        $this->fails(fn () => $this->service->saveAgreement($v2, $investor, $this->flat, ['committed_capital' => '1500000', 'profit_share_percent' => '50.5', 'effective_from' => now()->toDateString()], $this->officer), 'profit_share_percent');
        $this->service->submitAgreement($v2, $this->officer);
        $this->service->approveAgreement($v2, $this->manager);

        $this->assertSame('closed', $v1->fresh()->status);
        $this->assertSame(now()->subDay()->toDateString(), $v1->fresh()->effective_to->toDateString());
        $this->assertSame('active', $v2->fresh()->status);
        $this->assertSame(2, $v1->fresh()->versions()->count());
    }

    public function test_capital_contributions_returns_and_reversals(): void
    {
        $a = $this->agreement('25', capital: '500000');
        $investor = $a->investor;
        $key = (string) Str::uuid();
        $in = $this->service->recordCapital($a, 'contribution', ['amount' => '300000', 'transaction_date' => now()->toDateString(), 'payment_method' => 'bank'], $this->officer, $key);
        $this->assertTrue($in->is($this->service->recordCapital($a, 'contribution', ['amount' => '300000', 'transaction_date' => now()->toDateString()], $this->officer, $key)));
        $this->fails(fn () => $this->service->recordCapital($a, 'contribution', ['amount' => '200000.01', 'transaction_date' => now()->toDateString()], $this->officer, (string) Str::uuid()), 'amount');
        $this->fails(fn () => $this->service->recordCapital($a, 'capital_return', ['amount' => '1', 'transaction_date' => now()->toDateString()], $this->officer, (string) Str::uuid()), 'amount');

        try {
            $this->service->postCapital($in, $this->officer);
            $this->fail('Self posting must be refused.');
        } catch (ValidationException|HttpExceptionInterface) {
        }
        $this->service->postCapital($in, $this->manager);
        $this->assertSame(['capital' => '300000.00', 'payable' => '0.00', 'paid' => '0.00', 'profit_due' => '0.00'], InvestorLedger::balances($investor));
        $this->assertSame(1, FinancialEvent::query()->where('event_type', 'investment.received')->count());

        $out = $this->service->recordCapital($a, 'capital_return', ['amount' => '100000', 'transaction_date' => now()->toDateString(), 'payment_method' => 'cash'], $this->officer, (string) Str::uuid());
        $this->fails(fn () => $this->service->recordCapital($a, 'capital_return', ['amount' => '200000.01', 'transaction_date' => now()->toDateString()], $this->officer, (string) Str::uuid()), 'amount');
        $this->service->postCapital($out, $this->manager);
        $this->assertSame('200000.00', InvestorService::capitalBalance($investor->id, $this->flat->id));

        $second = $this->service->recordCapital($a, 'contribution', ['amount' => '50000', 'transaction_date' => now()->toDateString()], $this->officer, (string) Str::uuid());
        $this->service->postCapital($second, $this->manager);
        $this->service->reverseCapital($out, 'Bank bounced', $this->manager);
        $this->service->reverseCapital($in, 'Duplicate', $this->manager);
        $this->assertSame('50000.00', InvestorLedger::balances($investor)['capital']);
        $this->assertSame(1, InvestorLedgerEntry::query()->where('reversal_of_id', InvestorLedgerEntry::query()->where('source_id', $in->id)->where('entry_kind', 'capital_in')->value('id'))->count());
        $this->assertSame(1, FinancialEvent::query()->where('event_type', 'investment.withdrawn')->whereNotNull('reversal_of_id')->count());

        $this->service->closeAgreement($a, now()->toDateString(), 'Exit', $this->manager);
        try {
            $this->service->recordCapital($a->fresh(), 'contribution', ['amount' => '1', 'transaction_date' => now()->toDateString()], $this->officer, (string) Str::uuid());
            $this->fail('Contribution on a closed agreement must be refused.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $back = $this->service->recordCapital($a->fresh(), 'capital_return', ['amount' => '50000', 'transaction_date' => now()->toDateString()], $this->officer, (string) Str::uuid());
        $this->service->postCapital($back, $this->manager);
        $this->assertSame('0.00', InvestorLedger::balances($investor)['capital']);
    }
}
