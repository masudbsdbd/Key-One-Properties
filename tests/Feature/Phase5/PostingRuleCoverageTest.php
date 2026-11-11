<?php

namespace Tests\Feature\Phase5;

use App\Models\LedgerAccount;
use App\Models\User;
use App\Modules\Accounting\Rules\PostingRules;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Support\ChartOfAccounts;
use App\Support\FinancialEvents\FinancialEventTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionClass;
use Tests\TestCase;

class PostingRuleCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_financial_event_type_either_posts_or_is_deliberately_silent(): void
    {
        $rules = new ReflectionClass(PostingRules::class);
        $silent = $rules->getConstant('SILENT');
        $missing = [];
        foreach (FinancialEventTypes::all() as $type) {
            $method = lcfirst(str_replace(' ', '', ucwords(str_replace(['.', '_'], ' ', $type))));
            if (! in_array($type, $silent, true) && ! $rules->hasMethod($method)) {
                $missing[] = $type;
            }
        }

        $this->assertSame([], $missing, 'Event types with no posting rule.');
        $this->assertSame([], array_values(array_diff($silent, FinancialEventTypes::all())), 'Silent list names unknown event types.');
    }

    public function test_every_posting_key_used_by_the_rules_exists_in_the_chart(): void
    {
        $source = file_get_contents((new ReflectionClass(PostingRules::class))->getFileName());
        preg_match_all("/(?:->dr|->cr|netOf|forKey)\\('([a-z_]+)'/", $source, $matches);
        $keys = array_values(array_unique($matches[1]));
        $known = array_filter(array_column(ChartOfAccounts::ACCOUNTS, 3));

        $this->assertGreaterThan(15, count($keys));
        $this->assertSame([], array_values(array_diff($keys, $known)), 'Posting keys missing from the chart of accounts.');
        foreach ($keys as $key) {
            $this->assertTrue(LedgerAccount::forKey($key)->is_postable, $key);
        }
    }

    public function test_statements_export_to_pdf_and_print(): void
    {
        $viewer = $this->userWith(['accounting.reports.view', 'accounting.reports.export', 'accounting.ledger.view']);
        app(Ledger::class)->post(['type' => 'manual', 'date' => Carbon::now(), 'narration' => 'Seed'], [
            ['account' => 'customer_receivable', 'debit' => '120000'], ['account' => 'other_income', 'credit' => '120000'],
        ], User::factory()->create());
        $ledger = ['account' => LedgerAccount::forKey('customer_receivable')->public_id, 'period' => 'all'];

        foreach (['accounting.profit-loss' => [], 'accounting.balance-sheet' => [], 'accounting.cash-flow' => [], 'accounting.trial-balance' => [], 'accounting.projects' => [], 'accounting.ledger' => $ledger] as $route => $query) {
            $pdf = $this->actingAs($viewer)->get(route($route, $query + ['format' => 'pdf']))->assertOk();
            $this->assertStringStartsWith('%PDF', $pdf->getContent(), $route);
            $this->actingAs($viewer)->get(route($route, $query + ['format' => 'print']))->assertOk()->assertSee('Key One', false);
            $this->assertNotSame('', $this->actingAs($viewer)->get(route($route, $query + ['format' => 'csv']))->assertOk()->streamedContent(), $route);
        }
    }
}
