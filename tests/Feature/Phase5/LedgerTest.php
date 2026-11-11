<?php

namespace Tests\Feature\Phase5;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Project;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Services\Periods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private function ledger(): Ledger
    {
        return app(Ledger::class);
    }

    private function header(array $extra = []): array
    {
        return $extra + ['type' => 'manual', 'date' => Carbon::parse('2026-08-15'), 'narration' => 'Test'];
    }

    public function test_a_balanced_journal_posts_and_an_unbalanced_one_is_rejected(): void
    {
        $entry = $this->ledger()->post($this->header(), [
            ['account' => 'fixed_assets', 'debit' => '1000'], ['account' => 'owner_capital', 'credit' => '1000'], ['account' => 'other_income', 'debit' => 0, 'credit' => 0],
        ], $this->superAdmin());

        $this->assertSame('posted', $entry->status);
        $this->assertSame('1000.00', $entry->total_amount);
        $this->assertSame(2, $entry->lines()->count());
        $this->assertSame('Aug 2026', $entry->period->name);
        $this->assertSame('FY 2026–27', $entry->period->fiscalYear->name);
        $this->assertSame(12, AccountingPeriod::query()->count());
        $this->assertSame('2026-07-01', FiscalYear::query()->firstOrFail()->start_date->toDateString());

        foreach ([
            [['account' => 'fixed_assets', 'debit' => '1000'], ['account' => 'owner_capital', 'credit' => '999']],
            [['account' => 'fixed_assets', 'debit' => '1000']],
            [['account' => 'fixed_assets', 'debit' => '10', 'credit' => '10'], ['account' => 'owner_capital', 'credit' => '10']],
            [['account' => 'fixed_assets', 'debit' => '-5'], ['account' => 'owner_capital', 'credit' => '-5']],
            [['account' => 'wip', 'debit' => '5'], ['account' => 'owner_capital', 'credit' => '5']],
        ] as $lines) {
            try {
                $this->ledger()->post($this->header(), $lines);
                $this->fail('An invalid journal was posted.');
            } catch (ValidationException) {
            }
        }
        $this->assertSame(1, JournalEntry::query()->count());
        $this->assertSame((string) JournalLine::query()->sum('debit'), (string) JournalLine::query()->sum('credit'));
    }

    public function test_parent_and_inactive_accounts_cannot_be_posted_to(): void
    {
        $bank = LedgerAccount::query()->where('code', '1020')->firstOrFail();
        $this->expectException(ValidationException::class);
        $this->ledger()->post($this->header(), [['account' => $bank, 'debit' => '10'], ['account' => 'owner_capital', 'credit' => '10']]);
    }

    public function test_the_same_source_posts_once(): void
    {
        $lines = [['account' => 'fixed_assets', 'debit' => '250'], ['account' => 'owner_capital', 'credit' => '250']];
        $first = $this->ledger()->post($this->header(['source_key' => 'test:1']), $lines);
        $again = $this->ledger()->post($this->header(['source_key' => 'test:1']), [['account' => 'fixed_assets', 'debit' => '999'], ['account' => 'owner_capital', 'credit' => '999']]);

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, JournalEntry::query()->count());
        $this->assertSame('250.00', (string) JournalLine::query()->sum('debit'));
    }

    public function test_a_reversal_is_an_opposite_journal_linked_to_the_original(): void
    {
        $project = Project::factory()->create();
        $entry = $this->ledger()->post($this->header(), [
            ['account' => 'wip', 'debit' => '400', 'project_id' => $project->id], ['account' => 'wages_payable', 'credit' => '400'],
        ]);
        $reversal = $this->ledger()->reverse($entry, Carbon::parse('2026-08-20'), 'Entered twice', $this->superAdmin());

        $this->assertSame($entry->id, $reversal->reversal_of_id);
        $this->assertSame($reversal->id, $entry->fresh()->reversed_by_id);
        $this->assertSame('posted', $entry->fresh()->status);
        $line = $reversal->lines()->whereHas('account', fn ($q) => $q->where('system_key', 'wip'))->firstOrFail();
        $this->assertSame(['0.00', '400.00', $project->id], [$line->debit, $line->credit, $line->project_id]);
        $this->assertSame((string) JournalLine::query()->sum('debit'), (string) JournalLine::query()->sum('credit'));

        try {
            $this->ledger()->reverse($entry->fresh(), Carbon::parse('2026-08-21'), 'Again');
            $this->fail('Reversed twice.');
        } catch (ValidationException) {
        }
        $this->expectException(ValidationException::class);
        $this->ledger()->reverse($reversal, Carbon::parse('2026-08-21'), 'Undo');
    }

    public function test_a_locked_month_rejects_postings(): void
    {
        Periods::for(Carbon::parse('2026-08-15'))->update(['status' => 'locked']);

        try {
            $this->ledger()->post($this->header(), [['account' => 'fixed_assets', 'debit' => '10'], ['account' => 'owner_capital', 'credit' => '10']]);
            $this->fail('Posted into a locked month.');
        } catch (ValidationException $e) {
            $this->assertSame('Aug 2026 is locked. Use a date in an open month.', $e->errors()['accounting_date'][0]);
        }
        $this->assertSame(0, JournalEntry::query()->count());
        $this->ledger()->post($this->header(['date' => Carbon::parse('2026-09-01')]), [['account' => 'fixed_assets', 'debit' => '10'], ['account' => 'owner_capital', 'credit' => '10']]);
        $this->assertSame(1, JournalEntry::query()->count());
    }

    public function test_a_manual_journal_moves_draft_submit_approve_post(): void
    {
        $maker = $this->userWith(['accounting.journal.create']);
        $checker = $this->userWith(['accounting.journal.approve', 'accounting.journal.post']);
        $lines = [['account' => 'bank_charges', 'debit' => '75'], ['account' => 'owner_capital', 'credit' => '75']];

        $draft = $this->ledger()->saveDraft(null, ['date' => Carbon::parse('2026-08-15'), 'narration' => 'Charge'], $lines, $maker);
        $this->assertSame(['draft', 'manual', null], [$draft->status, $draft->journal_type, $draft->posted_at]);
        $draft = $this->ledger()->saveDraft($draft, ['date' => Carbon::parse('2026-08-16'), 'narration' => 'Bank charge'], [['account' => 'bank_charges', 'debit' => '80'], ['account' => 'owner_capital', 'credit' => '80']], $maker);
        $this->assertSame(['80.00', 2], [$draft->total_amount, $draft->lines()->count()]);

        try {
            $this->ledger()->postDraft($draft, $checker);
            $this->fail('Posted without approval.');
        } catch (ConflictHttpException) {
        }
        $this->ledger()->submit($draft, $maker);
        try {
            $this->ledger()->approve($draft, $maker);
            $this->fail('Approved own journal.');
        } catch (ValidationException) {
        }
        $this->ledger()->approve($draft, $checker);
        $posted = $this->ledger()->postDraft($draft, $checker);

        $this->assertSame(['posted', $checker->id, $checker->id], [$posted->status, $posted->approved_by, $posted->posted_by]);
        $this->expectException(ConflictHttpException::class);
        $this->ledger()->saveDraft($posted, ['date' => Carbon::parse('2026-08-16'), 'narration' => 'Edit'], $lines, $maker);
    }
}
