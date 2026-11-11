<?php

namespace Tests\Feature\Phase5;

use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Project;
use App\Modules\Accounting\Services\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalHttpTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        $id = fn (string $key) => LedgerAccount::forKey($key)->public_id;

        return $extra + [
            'accounting_date' => '2026-08-10', 'narration' => 'Bank charge for August', 'reference' => 'ADV-1',
            'lines' => [
                ['account' => $id('bank_charges'), 'debit' => '1,000.00', 'credit' => ''],
                ['account' => '', 'debit' => '', 'credit' => ''],
                ['account' => $id('owner_capital'), 'debit' => '', 'credit' => '1000'],
            ],
        ];
    }

    public function test_a_journal_is_drafted_submitted_approved_posted_and_reversed_over_http(): void
    {
        $maker = $this->userWith(['accounting.journal.view', 'accounting.journal.create']);
        $checker = $this->userWith(['accounting.journal.view', 'accounting.journal.approve', 'accounting.journal.post', 'accounting.journal.reverse']);

        $this->actingAs($maker)->get(route('accounting.journals.create'))->assertOk()->assertSee('Submit for Approval');
        $this->actingAs($maker)->ajax()->post(route('accounting.journals.store'), $this->payload())->assertOk();
        $journal = JournalEntry::query()->firstOrFail();
        $this->assertSame(['draft', '1000.00', 2], [$journal->status, $journal->total_amount, $journal->lines()->count()]);

        $this->actingAs($maker)->get(route('accounting.journals.edit', $journal))->assertOk()->assertSee('Bank charge for August');
        $this->actingAs($maker)->ajax()->put(route('accounting.journals.update', $journal), $this->payload(['then' => 'submit', 'lock_version' => $journal->lock_version]))->assertOk();
        $this->assertSame('submitted', $journal->fresh()->status);
        $this->actingAs($maker)->get(route('accounting.journals.edit', $journal))->assertForbidden();
        $this->actingAs($maker)->ajax()->post(route('accounting.journals.decide', [$journal, 'approve-post']))->assertForbidden();

        $this->actingAs($checker)->get(route('accounting.journals.show', $journal))->assertOk()->assertSee('Approve &amp; Post', false)->assertSee('Bank Charges');
        $this->actingAs($checker)->ajax()->post(route('accounting.journals.decide', [$journal, 'approve-post']))->assertOk();
        $this->assertSame('posted', $journal->fresh()->status);
        $this->actingAs($maker)->ajax()->delete(route('accounting.journals.destroy', $journal))->assertForbidden();

        $this->actingAs($checker)->ajax()->post(route('accounting.journals.reason.store', [$journal, 'reverse']), ['reason' => 'Wrong account', 'date' => '2026-08-01'])
            ->assertStatus(422)->assertJsonValidationErrors('date');
        $this->actingAs($checker)->ajax()->post(route('accounting.journals.reason.store', [$journal, 'reverse']), ['reason' => 'Wrong account', 'date' => '2026-08-12'])->assertOk();
        $reversal = JournalEntry::query()->where('reversal_of_id', $journal->id)->firstOrFail();
        $this->assertSame($reversal->id, $journal->fresh()->reversed_by_id);

        $html = $this->actingAs($checker)->ajax()->get(route('accounting.journals.table', ['status' => 'reversed']))->assertOk()->json('data.html');
        $this->assertStringContainsString($journal->journal_no, $html);
        $this->assertStringContainsString('Reversed by', $html);
        $this->actingAs($checker)->get(route('accounting.journals.show', $journal))->assertOk()->assertSee('Reversed by')->assertDontSee('Approve &amp; Post', false);
    }

    public function test_unbalanced_or_out_of_scope_journals_are_rejected(): void
    {
        $maker = $this->userWith(['accounting.journal.view', 'accounting.journal.create']);
        $payload = $this->payload();
        $payload['lines'][2]['credit'] = '999';
        $this->actingAs($maker)->ajax()->post(route('accounting.journals.store'), $payload)->assertStatus(422)->assertJsonValidationErrors('lines');

        $hidden = Project::factory()->create();
        $payload = $this->payload();
        $payload['lines'][0]['project'] = $hidden->public_id;
        $this->actingAs($maker)->ajax()->post(route('accounting.journals.store'), $payload)->assertStatus(422)->assertJsonValidationErrors('lines.0.project');

        $payload = $this->payload();
        $payload['lines'][0]['account'] = LedgerAccount::query()->where('code', '1020')->firstOrFail()->public_id;
        $this->actingAs($maker)->ajax()->post(route('accounting.journals.store'), $payload)->assertStatus(422)->assertJsonValidationErrors('lines.0.account');
        $this->assertSame(0, JournalEntry::query()->count());
    }

    public function test_accounting_screens_need_their_permissions(): void
    {
        $plain = $this->userWith(['dashboard.view', 'projects.view_all']);
        foreach (['accounting.journals.index', 'accounting.journals.table', 'accounting.journals.create', 'accounting.accounts.index', 'accounting.accounts.list', 'accounting.accounts.create'] as $route) {
            $this->actingAs($plain)->ajax()->get(route($route))->assertForbidden();
        }
        $this->actingAs($plain)->ajax()->post(route('accounting.journals.store'), $this->payload())->assertForbidden();
        $this->actingAs($plain)->ajax()->get(route('options.ledger-accounts'))->assertOk()->assertJsonCount(0, 'results');
        $this->assertStringNotContainsString('Chart of Accounts', $this->actingAs($plain)->get(route('dashboard'))->getContent());
    }

    public function test_chart_of_accounts_lists_balances_and_guards_system_accounts(): void
    {
        $admin = $this->userWith(['accounting.coa.view', 'accounting.coa.manage', 'accounting.journal.view']);
        app(Ledger::class)->post(['type' => 'manual', 'date' => now(), 'narration' => 'Seed'], [
            ['account' => 'fixed_assets', 'debit' => '5000'], ['account' => 'owner_capital', 'credit' => '5000'],
        ]);

        $html = $this->actingAs($admin)->ajax()->get(route('accounting.accounts.list'))->assertOk()->json('data.html');
        $this->assertStringContainsString('Fixed Assets', $html);
        $this->assertStringContainsString('5,000', $html);

        $this->actingAs($admin)->ajax()->post(route('accounting.accounts.store'), ['code' => '6400', 'name' => 'Office Rent', 'category' => 'expense',
            'parent' => LedgerAccount::query()->where('code', '6000')->firstOrFail()->public_id])->assertOk();
        $rent = LedgerAccount::query()->where('code', '6400')->firstOrFail();
        $this->assertSame(['6000', true, false], [$rent->parent->code, $rent->is_postable, $rent->is_system]);
        $this->actingAs($admin)->ajax()->post(route('accounting.accounts.store'), ['code' => '6400', 'name' => 'Again', 'category' => 'expense'])->assertStatus(422);
        $this->actingAs($admin)->ajax()->post(route('accounting.accounts.store'), ['code' => '6401', 'name' => 'Wrong parent', 'category' => 'income',
            'parent' => LedgerAccount::query()->where('code', '6000')->firstOrFail()->public_id])->assertStatus(422)->assertJsonValidationErrors('parent');

        $stock = LedgerAccount::forKey('stock');
        $this->actingAs($admin)->ajax()->put(route('accounting.accounts.update', $stock), ['code' => '9999', 'name' => 'Materials', 'is_active' => '0'])->assertStatus(409);
        $this->actingAs($admin)->ajax()->put(route('accounting.accounts.update', $stock), ['code' => '9999', 'name' => 'Materials', 'is_active' => '1'])->assertOk();
        $this->assertSame(['1300', 'Materials'], [$stock->fresh()->code, $stock->fresh()->name]);
        $this->actingAs($admin)->ajax()->delete(route('accounting.accounts.destroy', $stock))->assertStatus(409);
        $this->actingAs($admin)->ajax()->delete(route('accounting.accounts.destroy', $rent))->assertOk();
        $this->assertNull(LedgerAccount::query()->where('code', '6400')->first());
    }
}
