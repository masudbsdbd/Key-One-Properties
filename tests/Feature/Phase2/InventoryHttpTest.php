<?php

namespace Tests\Feature\Phase2;

use App\Models\Material;
use App\Models\MaterialBorrow;
use App\Models\MaterialCategory;
use App\Models\MaterialTransfer;
use App\Models\MaterialUnit;
use App\Models\Project;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockIssue;
use App\Models\StockIssueReturn;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Inventory\Services\StockReconciler;
use App\Support\Costing\CostAttribution;
use App\Support\Reconciliation\SubledgerReconciler;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryHttpTest extends TestCase
{
    private const STORE = [
        'projects.view', 'materials.view', 'materials.receive', 'materials.issue', 'materials.borrow.request', 'materials.borrow.return',
        'materials.transfer.request', 'materials.adjust.request', 'materials.valuation.view', 'materials.export',
    ];

    private const MANAGER = [
        'projects.view', 'materials.view', 'materials.borrow.approve', 'materials.borrow.issue', 'materials.borrow.convert',
        'materials.transfer.approve', 'materials.transfer.post', 'materials.adjust.approve', 'materials.movement.reverse', 'materials.valuation.view',
    ];

    private Project $a;

    private Project $b;

    private Warehouse $whA;

    private Warehouse $whB;

    private Material $rod;

    private User $keeper;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->a, $this->b] = [Project::factory()->create(), Project::factory()->create()];
        $this->keeper = $this->userWith(self::STORE);
        $this->manager = $this->userWith(self::MANAGER);
        foreach ([$this->a, $this->b] as $p) {
            $p->members()->attach([$this->keeper->id, $this->manager->id]);
        }
        $this->whA = $this->warehouse('WH-A', $this->a);
        $this->whB = $this->warehouse('WH-B', $this->b);

        $category = MaterialCategory::query()->create(['code' => 'STL', 'name' => 'Steel']);
        $unit = MaterialUnit::query()->create(['code' => 'KG', 'name' => 'Kilogram']);
        $this->rod = new Material(['sku' => 'ROD-12', 'name' => 'MS Rod 12mm']);
        $this->rod->category_id = $category->id;
        $this->rod->unit_id = $unit->id;
        $this->rod->save();
    }

    private function warehouse(string $code, ?Project $project): Warehouse
    {
        $w = new Warehouse(['code' => $code, 'name' => $code]);
        $w->project_id = $project?->id;
        $w->save();

        return $w;
    }

    private function receive(Warehouse $w, string $qty = '1000', string $cost = '95'): void
    {
        $this->actingAs($this->keeper)->ajax()->post(route('stock.receive'), [
            'warehouse' => $w->public_id, 'received_at' => now()->toDateString(), 'source' => 'opening', 'idempotency_key' => (string) Str::uuid(),
            'lines' => [['material' => $this->rod->public_id, 'quantity' => $qty, 'unit_cost' => $cost]],
        ])->assertOk();
    }

    public function test_receive_validation_and_idempotent_retry(): void
    {
        $key = (string) Str::uuid();
        $payload = ['warehouse' => $this->whA->public_id, 'received_at' => now()->toDateString(), 'source' => 'opening', 'idempotency_key' => $key,
            'lines' => [['material' => $this->rod->public_id, 'quantity' => '100', 'unit_cost' => '95']]];

        $this->actingAs($this->keeper)->ajax()->post(route('stock.receive'), ['lines' => [['material' => $this->rod->public_id, 'quantity' => '-1']]] + $payload)
            ->assertStatus(422)->assertJsonValidationErrors(['lines.0.quantity']);
        $this->actingAs($this->keeper)->ajax()->post(route('stock.receive'), $payload)->assertOk();
        $this->actingAs($this->keeper)->ajax()->post(route('stock.receive'), $payload)->assertOk();

        $this->assertSame('100.000', StockBalance::query()->where('warehouse_id', $this->whA->id)->value('owned_qty'));
    }

    public function test_issue_beyond_stock_returns_conflict_and_issue_posts_material_cost(): void
    {
        $this->receive($this->whA, '10', '100');
        $issue = fn (string $qty) => $this->actingAs($this->keeper)->ajax()->post(route('stock.issue'), [
            'warehouse' => $this->whA->public_id, 'project' => $this->a->public_id, 'issued_at' => now()->toDateString(), 'idempotency_key' => (string) Str::uuid(),
            'lines' => [['material' => $this->rod->public_id, 'quantity' => $qty]],
        ]);

        $issue('11')->assertStatus(409);
        $issue('4')->assertOk();
        $this->assertSame('400.00', CostAttribution::totals($this->a)['material']);
    }

    public function test_project_warehouse_cannot_issue_to_another_project(): void
    {
        $this->receive($this->whA, '10', '100');

        $this->actingAs($this->keeper)->ajax()->post(route('stock.issue'), [
            'warehouse' => $this->whA->public_id, 'project' => $this->b->public_id, 'issued_at' => now()->toDateString(), 'idempotency_key' => (string) Str::uuid(),
            'lines' => [['material' => $this->rod->public_id, 'quantity' => '1']],
        ])->assertStatus(422)->assertJsonValidationErrors('project');
    }

    public function test_full_borrow_flow_over_http(): void
    {
        $this->receive($this->whA, '200', '100');

        $this->actingAs($this->keeper)->ajax()->post(route('borrows.store'), [
            'source_warehouse' => $this->whA->public_id, 'destination_warehouse' => $this->whB->public_id,
            'lines' => [['material' => $this->rod->public_id, 'quantity' => '50']],
        ])->assertOk();
        $borrow = MaterialBorrow::query()->firstOrFail();
        $line = $borrow->lines()->firstOrFail();

        $this->actingAs($this->manager)->ajax()->post(route('borrows.issue', $borrow))->assertStatus(409);
        $this->actingAs($this->manager)->ajax()->post(route('borrows.approve', $borrow), ['approved' => [0 => '60']])->assertStatus(422)->assertJsonValidationErrors('approved.0');
        $this->actingAs($this->manager)->ajax()->post(route('borrows.approve', $borrow), ['approved' => [0 => '40']])->assertOk();
        $this->actingAs($this->manager)->ajax()->post(route('borrows.issue', $borrow))->assertOk();
        $this->assertSame('40.000', StockBalance::query()->where('warehouse_id', $this->whB->id)->value('borrowed_in_qty'));

        $this->actingAs($this->keeper)->ajax()->post(route('borrows.settle', [$borrow, 'return']), ['quantities' => [0 => '50'], 'idempotency_key' => (string) Str::uuid()])->assertStatus(409);
        $this->actingAs($this->keeper)->ajax()->post(route('borrows.settle', [$borrow, 'return']), ['quantities' => [0 => '10'], 'idempotency_key' => (string) Str::uuid()])->assertOk();

        $this->actingAs($this->keeper)->ajax()->post(route('borrows.settle', [$borrow, 'convert']), ['quantities' => [0 => '5'], 'reason' => 'x', 'idempotency_key' => (string) Str::uuid()])->assertForbidden();
        $this->actingAs($this->manager)->ajax()->post(route('borrows.settle', [$borrow, 'convert']), ['quantities' => [0 => '30'], 'reason' => 'Keep it', 'idempotency_key' => (string) Str::uuid()])->assertOk();

        $this->assertSame('closed', $borrow->fresh()->status);
        $this->assertSame('3000.00', StockBalance::query()->where('warehouse_id', $this->whB->id)->value('owned_value'));
        $this->actingAs($this->manager)->get(route('borrows.show', $borrow))->assertOk()->assertSee($borrow->borrow_no);
    }

    public function test_out_of_scope_user_cannot_see_or_act_on_borrows_and_warehouses(): void
    {
        $this->receive($this->whA);
        $this->actingAs($this->keeper)->ajax()->post(route('borrows.store'), [
            'source_warehouse' => $this->whA->public_id, 'destination_warehouse' => $this->whB->public_id,
            'lines' => [['material' => $this->rod->public_id, 'quantity' => '5']],
        ])->assertOk();
        $borrow = MaterialBorrow::query()->firstOrFail();
        $outsider = $this->userWith(array_merge(self::MANAGER, self::STORE));

        $this->actingAs($outsider)->get(route('borrows.show', $borrow))->assertForbidden();
        $this->actingAs($outsider)->ajax()->post(route('borrows.approve', $borrow), ['approved' => []])->assertForbidden();
        $this->assertStringNotContainsString($borrow->borrow_no, $this->actingAs($outsider)->ajax()->get(route('borrows.table'))->json('data.html'));
        $this->assertSame([], $this->actingAs($outsider)->ajax()->get(route('options.warehouses'))->json('results'));
        $this->actingAs($outsider)->ajax()->post(route('stock.receive'), [
            'warehouse' => $this->whA->public_id, 'received_at' => now()->toDateString(), 'source' => 'opening', 'idempotency_key' => (string) Str::uuid(),
            'lines' => [['material' => $this->rod->public_id, 'quantity' => '1', 'unit_cost' => '1']],
        ])->assertStatus(422)->assertJsonValidationErrors('warehouse');
    }

    public function test_transfer_preview_and_lifecycle(): void
    {
        $this->receive($this->whA, '100', '90');
        $payload = ['source_warehouse' => $this->whA->public_id, 'destination_warehouse' => $this->whB->public_id,
            'lines' => [['material' => $this->rod->public_id, 'quantity' => '150']]];

        $this->actingAs($this->keeper)->ajax()->post(route('transfers.preview'), $payload)->assertOk()->assertJsonPath('data.ok', false);
        $payload['lines'][0]['quantity'] = '25';
        $this->actingAs($this->keeper)->ajax()->post(route('transfers.preview'), $payload)->assertOk()->assertJsonPath('data.ok', true);
        $this->actingAs($this->keeper)->ajax()->post(route('transfers.store'), $payload + ['idempotency_key' => (string) Str::uuid()])->assertOk();
        $transfer = MaterialTransfer::query()->firstOrFail();

        $this->actingAs($this->manager)->ajax()->post(route('transfers.post', $transfer))->assertStatus(409);
        $this->actingAs($this->manager)->ajax()->post(route('transfers.approve', $transfer))->assertOk();
        $this->actingAs($this->manager)->ajax()->post(route('transfers.post', $transfer))->assertOk();
        $this->actingAs($this->manager)->ajax()->post(route('transfers.post', $transfer))->assertStatus(409);

        $this->assertSame('2250.00', StockBalance::query()->where('warehouse_id', $this->whB->id)->value('owned_value'));
        $this->assertDatabaseHas('financial_events', ['event_type' => 'material.transferred', 'amount' => '2250.00']);
    }

    public function test_adjustment_requires_another_approver_and_hides_value_without_permission(): void
    {
        $this->receive($this->whA, '10', '100');
        $this->actingAs($this->keeper)->ajax()->post(route('adjustments.store'), [
            'warehouse' => $this->whA->public_id, 'reason' => 'Count', 'idempotency_key' => (string) Str::uuid(),
            'lines' => [['material' => $this->rod->public_id, 'quantity' => '-2']],
        ])->assertOk();
        $adjustment = StockAdjustment::query()->firstOrFail();

        $this->actingAs($this->manager)->ajax()->post(route('adjustments.approve', $adjustment))->assertOk();
        $this->assertSame('8.000', StockBalance::query()->where('warehouse_id', $this->whA->id)->value('owned_qty'));

        $noValue = $this->userWith(['projects.view', 'materials.view']);
        $this->a->members()->attach($noValue->id);
        $html = $this->actingAs($noValue)->ajax()->get(route('stock.table'))->json('data.html');
        $this->assertStringNotContainsString('800.00', $html);
        $this->actingAs($noValue)->get(route('stock.movements.export'))->assertForbidden();
    }

    public function test_a_project_issues_stock_from_its_own_page(): void
    {
        $central = $this->warehouse('WH-CEN', null);
        $this->receive($this->whA);
        $this->receive($central, '500', '90');

        $form = fn (array $q) => $this->actingAs($this->keeper)->ajax()->get(route('stock.issue.form', $q))->assertOk()->json('data.html');
        $html = $form(['project' => $this->a->public_id]);
        $this->assertStringContainsString('name="project" id="issue-project" value="'.$this->a->public_id.'"', $html);
        $this->assertStringContainsString('value="'.$this->whA->public_id.'" selected', $html);
        $this->assertStringContainsString(e(route('options.warehouses', ['issue_to' => $this->a->public_id])), $html);
        $html = $form(['project' => $this->a->public_id, 'warehouse' => $this->whB->public_id, 'material' => $this->rod->public_id]);
        $this->assertStringNotContainsString('value="'.$this->whB->public_id.'" selected', $html);
        $this->assertStringContainsString($this->rod->public_id, $html);
        $this->actingAs($this->userWith(['materials.issue']))->ajax()->get(route('stock.issue.form', ['project' => $this->a->public_id]))->assertNotFound();

        $options = collect($this->actingAs($this->keeper)->ajax()->get(route('options.warehouses', ['issue_to' => $this->a->public_id]))->json('results'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$this->whA->public_id, $central->public_id], $options);

        $rows = $this->actingAs($this->keeper)->ajax()->get(route('stock.table', ['project' => $this->a->public_id]))->json('data.html');
        $this->assertStringContainsString(e(route('stock.issue.form', ['project' => $this->a->public_id, 'warehouse' => $this->whA->public_id, 'material' => $this->rod->public_id])), $rows);

        $this->actingAs($this->keeper)->ajax()->post(route('stock.issue'), [
            'warehouse' => $central->public_id, 'project' => $this->a->public_id, 'issued_at' => now()->toDateString(), 'purpose' => 'Roof slab',
            'idempotency_key' => (string) Str::uuid(), 'lines' => [['material' => $this->rod->public_id, 'quantity' => '100']],
        ])->assertOk();
        $this->assertSame('9000.00', CostAttribution::totals($this->a)['material']);

        $issues = $this->actingAs($this->keeper)->ajax()->get(route('stock.issues.table', ['project' => $this->a->public_id]))->assertOk()->json('data.html');
        $this->assertStringContainsString('Roof slab', $issues);
        $this->assertStringContainsString('MS Rod 12mm', $issues);
        $this->assertStringContainsString('9,000', $issues);
        $this->assertStringNotContainsString('Reverse', $issues);
        $this->assertStringContainsString('Reverse', $this->actingAs($this->manager)->ajax()->get(route('stock.issues.table', ['project' => $this->a->public_id]))->json('data.html'));
        $this->assertStringContainsString('No issues', $this->actingAs($this->keeper)->ajax()->get(route('stock.issues.table', ['project' => $this->b->public_id]))->json('data.html'));
        $this->actingAs($this->userWith(['materials.view']))->ajax()->get(route('stock.issues.table', ['project' => $this->a->public_id]))->assertNotFound();
    }

    public function test_part_of_an_issue_comes_back_to_the_store_at_its_cost(): void
    {
        $this->receive($this->whA, '3', '10');
        $this->receive($this->whA, '4', '11');
        $this->actingAs($this->keeper)->ajax()->post(route('stock.issue'), [
            'warehouse' => $this->whA->public_id, 'project' => $this->a->public_id, 'issued_at' => now()->toDateString(),
            'idempotency_key' => (string) Str::uuid(), 'lines' => [['material' => $this->rod->public_id, 'quantity' => '6']],
        ])->assertOk();
        $issue = StockIssue::query()->with('lines')->sole();
        $issued = (string) $issue->lines->first()->total_value;
        $this->assertSame($issued, CostAttribution::totals($this->a)['material']);

        $this->actingAs($this->keeper)->ajax()->get(route('stock.issues.return.form', $issue))->assertOk()->assertJsonPath('data.html', fn ($h) => str_contains($h, 'quantities[0]'));
        $this->actingAs($this->userWith(['materials.issue']))->ajax()->get(route('stock.issues.return.form', $issue))->assertForbidden();
        $return = fn (string $qty) => $this->actingAs($this->keeper)->ajax()->post(route('stock.issues.return', $issue), [
            'returned_at' => now()->toDateString(), 'reason' => 'Left over', 'idempotency_key' => (string) Str::uuid(), 'quantities' => [0 => $qty],
        ]);

        $return('7')->assertStatus(422)->assertJsonValidationErrors('quantities.0');
        $return('')->assertStatus(422)->assertJsonValidationErrors('quantities');
        $return('2')->assertOk()->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Returned to store (SRN-'));
        $first = StockIssueReturn::query()->with('lines')->sole()->lines->first();
        $this->assertSame(2.0, (float) $first->quantity);
        $this->assertSame(3.0, (float) StockBalance::query()->where('warehouse_id', $this->whA->id)->value('owned_qty'));
        $this->assertEqualsWithDelta((float) $issued - (float) $first->total_value, (float) CostAttribution::totals($this->a)['material'], 0.001);

        // The last part brings back exactly what is left, so the project cost lands on zero to the paisa.
        $return('4')->assertOk();
        $this->assertSame('0.00', CostAttribution::totals($this->a)['material']);
        $this->assertSame(7.0, (float) StockBalance::query()->where('warehouse_id', $this->whA->id)->value('owned_qty'));
        $this->assertFalse($issue->fresh()->load('lines')->isReturnable());
        $this->actingAs($this->keeper)->ajax()->get(route('stock.issues.return.form', $issue))->assertStatus(409);
        $this->actingAs($this->manager)->ajax()->post(route('stock.issues.reverse', $issue), ['reason' => 'x'])->assertStatus(409);

        $this->assertSame([], app(StockReconciler::class)->differences());
        $this->assertSame([], app(SubledgerReconciler::class)->differences());
        $this->assertDatabaseHas('financial_events', ['event_type' => 'material.site_returned']);

        $issues = $this->actingAs($this->keeper)->ajax()->get(route('stock.issues.table', ['project' => $this->a->public_id]))->json('data.html');
        $this->assertStringContainsString('6 returned', $issues);
        $this->assertStringNotContainsString(route('stock.issues.return.form', $issue), $issues);
        $movements = $this->actingAs($this->keeper)->ajax()->get(route('stock.movements.table', ['issue' => $issue->public_id]))->json('data.html');
        $this->assertSame(3, substr_count($movements, '<tr'));
    }
}
