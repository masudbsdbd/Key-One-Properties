<?php

namespace Tests\Feature\Phase2;

use App\Models\Material;
use App\Models\MaterialBorrow;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Project;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Inventory\Services\BorrowService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\StockReconciler;
use App\Modules\Inventory\Services\TransferService;
use App\Support\Costing\CostAttribution;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class StockLedgerTest extends TestCase
{
    private Material $cement;

    private Warehouse $a;

    private Warehouse $b;

    private User $storekeeper;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $category = MaterialCategory::query()->create(['code' => 'CEM', 'name' => 'Cement']);
        $unit = MaterialUnit::query()->create(['code' => 'BAG', 'name' => 'Bag']);
        $this->cement = new Material(['sku' => 'CEM-01', 'name' => 'Cement 50kg']);
        $this->cement->category_id = $category->id;
        $this->cement->unit_id = $unit->id;
        $this->cement->save();

        $this->a = $this->warehouse(Project::factory()->create(), 'WH-A');
        $this->b = $this->warehouse(Project::factory()->create(), 'WH-B');
        $this->storekeeper = User::factory()->create();
        $this->approver = User::factory()->create();
    }

    private function warehouse(?Project $project, string $code): Warehouse
    {
        $w = new Warehouse(['code' => $code, 'name' => $code]);
        $w->project_id = $project?->id;
        $w->save();

        return $w;
    }

    private function balance(Warehouse $w): StockBalance
    {
        return StockBalance::query()->where('warehouse_id', $w->id)->where('material_id', $this->cement->id)->firstOrFail();
    }

    private function receive(Warehouse $w, string $qty, string $cost): void
    {
        app(InventoryService::class)->receive($w, [['material' => $this->cement, 'quantity' => $qty, 'unit_cost' => $cost]],
            ['received_at' => now()->toDateString(), 'source' => 'opening'], $this->storekeeper, (string) Str::uuid());
    }

    private function borrow(string $qty): MaterialBorrow
    {
        $service = app(BorrowService::class);
        $borrow = $service->request($this->a, $this->b, [['material' => $this->cement, 'quantity' => $qty]], null, $this->storekeeper);
        $service->approve($borrow, [], $this->approver);
        $service->issue($borrow, $this->approver);

        return $borrow->fresh('lines');
    }

    public function test_weighted_average_cost(): void
    {
        $this->receive($this->a, '100', '500');
        $this->receive($this->a, '100', '600');

        $b = $this->balance($this->a);
        $this->assertSame('200.000', $b->owned_qty);
        $this->assertSame('110000.00', $b->owned_value);
        $this->assertSame('550.0000', $b->avg_unit_cost);
    }

    public function test_borrow_return_convert_and_over_convert(): void
    {
        $this->receive($this->a, '100', '500');
        $this->receive($this->a, '100', '600');
        $borrow = $this->borrow('20');
        $line = $borrow->lines->first();

        // 2. Borrow moves custody only.
        $this->assertSame('180.000', $this->balance($this->a)->owned_qty);
        $this->assertSame('20.000', $this->balance($this->a)->borrowed_out_qty);
        $this->assertSame('110000.00', $this->balance($this->a)->owned_value);
        $this->assertSame('0.000', $this->balance($this->b)->owned_qty);
        $this->assertSame('20.000', $this->balance($this->b)->borrowed_in_qty);
        $this->assertSame('0.00', $this->balance($this->b)->owned_value);
        $this->assertSame('550.0000', $line->locked_unit_cost);

        // 3. Return 8.
        app(BorrowService::class)->return($borrow, [$line->id => '8'], $this->storekeeper, 'ret-1');
        $this->assertSame('12.000', $line->fresh()->outstanding());
        $this->assertSame('partially_returned', $borrow->fresh()->status);

        // 4. Convert 7: value moves 7 x 550 = 3850 from A to B.
        $transfer = app(BorrowService::class)->convert($borrow->fresh(), [$line->id => '7'], 'Keep', $this->approver, 'conv-1');
        $this->assertSame('5.000', $line->fresh()->outstanding());
        $this->assertSame('106150.00', $this->balance($this->a)->owned_value);
        $this->assertSame('3850.00', $this->balance($this->b)->owned_value);
        $this->assertSame('7.000', $this->balance($this->b)->owned_qty);
        $this->assertSame('partially_converted', $borrow->fresh()->status);
        $this->assertSame('conversion', $transfer->kind);
        $this->assertDatabaseHas('financial_events', ['event_type' => 'material.transferred', 'amount' => '3850.00']);

        // 5. Converting 6 more fails with no side effects.
        $before = [$this->balance($this->a)->toArray(), $this->balance($this->b)->toArray(), StockMovement::query()->count()];
        try {
            app(BorrowService::class)->convert($borrow->fresh(), [$line->id => '6'], null, $this->approver, 'conv-2');
            $this->fail('Over-conversion should fail.');
        } catch (ConflictHttpException) {
        }
        $this->assertEquals($before, [$this->balance($this->a)->toArray(), $this->balance($this->b)->toArray(), StockMovement::query()->count()]);

        // Retried return with the same key does nothing.
        app(BorrowService::class)->return($borrow->fresh(), [$line->id => '8'], $this->storekeeper, 'ret-1');
        $this->assertSame('5.000', $line->fresh()->outstanding());

        // Return the rest: borrow closes; borrower holds only its converted, owned stock.
        app(BorrowService::class)->return($borrow->fresh(), [$line->id => '5'], $this->storekeeper, 'ret-2');
        $this->assertSame('closed', $borrow->fresh()->status);
        $this->assertSame('0.000', $this->balance($this->b)->borrowed_in_qty);
        $this->assertSame('0.000', $this->balance($this->a)->borrowed_out_qty);
        $this->assertSame([], app(StockReconciler::class)->differences());
    }

    public function test_borrowed_stock_cannot_be_issued_by_borrower_and_no_negative_stock(): void
    {
        $this->receive($this->a, '10', '500');
        $this->borrow('10');

        $this->expectException(ConflictHttpException::class);
        app(InventoryService::class)->issue($this->b, $this->b->project, null, [['material' => $this->cement, 'quantity' => '1']],
            ['issued_at' => now()->toDateString()], $this->storekeeper, (string) Str::uuid());
    }

    public function test_last_units_can_only_be_issued_once(): void
    {
        $this->receive($this->a, '5', '500');
        $issue = fn (string $key) => app(InventoryService::class)->issue($this->a, $this->a->project, null,
            [['material' => $this->cement, 'quantity' => '5']], ['issued_at' => now()->toDateString()], $this->storekeeper, $key);

        $issue((string) Str::uuid());
        try {
            $issue((string) Str::uuid());
            $this->fail('Second issue should fail.');
        } catch (ConflictHttpException) {
        }

        $this->assertSame('0.000', $this->balance($this->a)->owned_qty);
        $this->assertSame('0.00', $this->balance($this->a)->owned_value);
        $this->assertSame('2500.00', CostAttribution::totals($this->a->project)['material']);
    }

    public function test_issue_retry_with_same_key_posts_once_and_reversal_restores(): void
    {
        $this->receive($this->a, '10', '500');
        $key = (string) Str::uuid();
        $issue = app(InventoryService::class)->issue($this->a, $this->a->project, null, [['material' => $this->cement, 'quantity' => '4']],
            ['issued_at' => now()->toDateString()], $this->storekeeper, $key);
        app(InventoryService::class)->issue($this->a, $this->a->project, null, [['material' => $this->cement, 'quantity' => '4']],
            ['issued_at' => now()->toDateString()], $this->storekeeper, $key);

        $this->assertSame('6.000', $this->balance($this->a)->owned_qty);
        $this->assertSame('2000.00', CostAttribution::totals($this->a->project)['material']);

        // 7. Reversal restores stock and attribution; original stays visible.
        app(InventoryService::class)->reverseIssue($issue, 'Wrong project', $this->approver);
        $this->assertSame('10.000', $this->balance($this->a)->owned_qty);
        $this->assertSame('5000.00', $this->balance($this->a)->owned_value);
        $this->assertSame('0.00', CostAttribution::totals($this->a->project)['material']);
        $this->assertSame(1, StockMovement::query()->where('movement_type', 'issue')->count());
        $this->assertSame(1, StockMovement::query()->where('movement_type', 'reversal')->count());
    }

    public function test_direct_transfer_moves_value_once_and_can_be_reversed(): void
    {
        $this->receive($this->a, '100', '500');
        $service = app(TransferService::class);
        $transfer = $service->request($this->a, $this->b, [['material' => $this->cement, 'quantity' => '30']], 'Need', $this->storekeeper, (string) Str::uuid());

        try {
            $service->approve($transfer, $this->storekeeper);
            $this->fail('Self approval should fail.');
        } catch (ValidationException) {
        }
        $service->approve($transfer, $this->approver);
        $service->post($transfer, $this->approver);

        $this->assertSame('35000.00', $this->balance($this->a)->owned_value);
        $this->assertSame('15000.00', $this->balance($this->b)->owned_value);

        $service->reverse($transfer->fresh(), 'Mistake', $this->approver);
        $this->assertSame('50000.00', $this->balance($this->a)->owned_value);
        $this->assertSame('0.00', $this->balance($this->b)->owned_value);
        $this->assertSame([], app(StockReconciler::class)->differences());
    }

    public function test_self_transfer_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        app(TransferService::class)->request($this->a, $this->a, [['material' => $this->cement, 'quantity' => '1']], null, $this->storekeeper, (string) Str::uuid());
    }

    public function test_reconciliation_detects_tampered_balance(): void
    {
        $this->receive($this->a, '10', '500');
        $this->assertSame([], app(StockReconciler::class)->differences());

        StockBalance::query()->whereKey($this->balance($this->a)->id)->update(['owned_qty' => '11']);
        $diffs = app(StockReconciler::class)->differences();
        $this->assertCount(1, $diffs);
        $this->assertSame('owned_qty', $diffs[0]['field']);
    }

    public function test_movements_are_immutable(): void
    {
        $this->receive($this->a, '10', '500');

        $this->expectException(\LogicException::class);
        StockMovement::query()->firstOrFail()->update(['quantity' => '1']);
    }
}
