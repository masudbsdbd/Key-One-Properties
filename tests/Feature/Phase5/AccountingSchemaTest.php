<?php

namespace Tests\Feature\Phase5;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Modules\Accounting\Support\ChartOfAccounts;
use App\Support\Permissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AccountingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_chart_of_accounts_is_installed_once_with_unique_keys(): void
    {
        $this->assertSame(count(ChartOfAccounts::ACCOUNTS), LedgerAccount::query()->count());
        ChartOfAccounts::install();
        $this->assertSame(count(ChartOfAccounts::ACCOUNTS), LedgerAccount::query()->count());

        $this->assertSame('1200', LedgerAccount::forKey('customer_receivable')->code);
        $this->assertTrue(LedgerAccount::forKey('wip')->isDebitNormal());
        $this->assertFalse(LedgerAccount::forKey('investor_capital')->isDebitNormal());
        $this->assertSame('1000', LedgerAccount::query()->where('code', '1020')->firstOrFail()->parent->code);
        $this->assertFalse(LedgerAccount::query()->where('code', '1020')->firstOrFail()->is_postable);
        foreach (ChartOfAccounts::ACCOUNTS as $code => [, $category]) {
            $this->assertArrayHasKey($category, ChartOfAccounts::CATEGORIES, $code);
        }
    }

    public function test_a_line_cannot_carry_both_sides_and_posted_journals_are_frozen(): void
    {
        $entry = JournalEntry::query()->create(['journal_no' => 'JV-T-1', 'journal_type' => 'manual', 'accounting_date' => '2026-07-01', 'narration' => 'Test', 'status' => 'draft']);
        $account = LedgerAccount::forKey('stock');

        try {
            JournalLine::query()->create(['journal_entry_id' => $entry->id, 'line_no' => 1, 'account_id' => $account->id, 'debit' => '10', 'credit' => '10']);
            $this->fail('Both sides were accepted.');
        } catch (QueryException) {
        }

        $line = JournalLine::query()->create(['journal_entry_id' => $entry->id, 'line_no' => 1, 'account_id' => $account->id, 'debit' => '10']);
        $entry->update(['status' => 'posted']);

        foreach ([fn () => $entry->update(['narration' => 'Changed']), fn () => $entry->delete(), fn () => $line->fresh()->update(['debit' => '11']), fn () => $line->fresh()->delete()] as $change) {
            try {
                $change();
                $this->fail('A posted journal was changed.');
            } catch (LogicException) {
            }
        }
        $this->assertSame('Test', $entry->fresh()->narration);
        $this->assertSame('10.00', $line->fresh()->debit);
    }

    public function test_accountant_roles_are_seeded_with_accounting_permissions_only_where_meant(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $accounting = array_values(array_filter(Permissions::all(), fn ($p) => str_starts_with($p, 'accounting.')));

        $accountant = Role::findByName('Accountant', 'web')->permissions->pluck('name');
        $this->assertSame([], array_values(array_diff($accounting, $accountant->all())));
        $viewer = Role::findByName('Accounts Viewer', 'web')->permissions->pluck('name');
        $this->assertFalse($viewer->contains('accounting.journal.post'));
        $this->assertTrue($viewer->contains('accounting.reports.view'));
        foreach (['Sales Executive', 'Project Manager', 'Viewer', 'Investor', 'Customer'] as $role) {
            $this->assertSame([], Role::findByName($role, 'web')->permissions->pluck('name')->filter(fn ($p) => str_starts_with($p, 'accounting.'))->all(), $role);
        }
    }
}
