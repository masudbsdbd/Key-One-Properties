<?php

namespace Database\Seeders;

use App\Models\ConstructionProgressUpdate;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\LabourEntry;
use App\Models\Material;
use App\Models\MaterialBorrow;
use App\Models\MaterialTransfer;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\PurchaseReturn;
use App\Models\StockAdjustment;
use App\Models\StockIssue;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkPackage;
use App\Modules\Construction\Services\ConstructionService;
use App\Modules\Inventory\Services\BorrowService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\TransferService;
use App\Modules\Purchasing\Services\PurchasingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Phase 2 sample data for local development only. Each section is skipped when its table already has rows.
 */
class ConstructionSampleDataSeeder extends Seeder
{
    private User $admin;

    private User $engineer;

    public function run(): void
    {
        $admin = User::query()->whereHas('roles', fn ($q) => $q->where('name', config('keyone.super_admin_role')))->first();
        $engineer = User::query()->where('email', 'robin@example.test')->first();
        if (! $admin || ! $engineer || ! Project::query()->exists()) {
            return;
        }
        $this->admin = $admin;
        $this->engineer = $engineer;

        $this->workPackages();
        $this->milestones();
        $this->progress();
        $this->labour();
        $this->stockIssues();
        $this->transfers();
        $this->adjustments();
        $this->borrows();
        $this->purchaseReturns();
        $this->customerNotes();
    }

    private function projects()
    {
        return Project::query()->with('stages')->orderBy('id')->get();
    }

    private function day(int $daysAgo): string
    {
        return now()->subDays($daysAgo)->toDateString();
    }

    private function material(string $sku): Material
    {
        return Material::query()->where('sku', $sku)->firstOrFail();
    }

    private function projectWarehouse(Project $project): Warehouse
    {
        return Warehouse::query()->where('project_id', $project->id)->firstOrFail();
    }

    private function workPackages(): void
    {
        if (WorkPackage::query()->exists()) {
            return;
        }

        $plan = [
            ['WP-PIL', 'Piling Work', 1, 6500000, -240, -180, 'completed', 100],
            ['WP-FDN', 'Foundation & Pile Cap', 1, 8200000, -180, -130, 'completed', 100],
            ['WP-GF', 'Ground Floor Slab', 2, 4300000, -130, -100, 'completed', 100],
            ['WP-STR1', 'Structure 1st-4th Floor', 2, 12500000, -100, -10, 'in_progress', 65],
            ['WP-STR2', 'Structure 5th-8th Floor', 2, 12000000, -10, 90, 'in_progress', 10],
            ['WP-BRK', 'Brickwork Lower Floors', 3, 5600000, -20, 120, 'in_progress', 15],
            ['WP-ELC', 'Electrical Conduit', 4, 3800000, 30, 200, 'not_started', 0],
            ['WP-PLB', 'Plumbing & Sanitary', 4, 4100000, 45, 220, 'not_started', 0],
            ['WP-FIN', 'Tiles & Painting', 5, 9500000, 180, 330, 'not_started', 0],
        ];

        foreach ($this->projects() as $index => $project) {
            $rows = $project->status === 'active' ? $plan : array_slice($plan, 0, 3);
            $scale = 1 - $index * 0.15;
            foreach ($rows as [$code, $name, $stage, $budget, $start, $end, $status, $progress]) {
                if ($project->status !== 'active') {
                    [$status, $progress, $start, $end] = ['not_started', 0, $start + 300, $end + 300];
                }
                if ($index === 1 && $progress > 0 && $progress < 100) {
                    $progress = max(5, $progress - 20);
                }
                $package = new WorkPackage([
                    'code' => $code, 'name' => $name, 'budget_amount' => round($budget * $scale, 2),
                    'planned_start_at' => now()->addDays($start)->toDateString(),
                    'planned_end_at' => now()->addDays($end)->toDateString(),
                    'status' => $status,
                ]);
                $package->project_id = $project->id;
                $package->stage_id = $project->stages[$stage]->id ?? null;
                $package->responsible_user_id = $this->engineer->id;
                $package->progress_percent = $progress;
                $package->created_by = $this->admin->id;
                $package->save();
            }
        }
    }

    private function milestones(): void
    {
        if (Milestone::query()->exists()) {
            return;
        }

        $plan = [
            ['Piling complete', 'WP-PIL', -180, true],
            ['Foundation cast', 'WP-FDN', -130, true],
            ['Ground floor slab cast', 'WP-GF', -100, true],
            ['4th floor roof cast', 'WP-STR1', -5, false],
            ['8th floor roof cast', 'WP-STR2', 90, false],
            ['Brickwork up to 4th floor', 'WP-BRK', 60, false],
            ['Electrical rough-in done', 'WP-ELC', 200, false],
        ];

        foreach ($this->projects() as $project) {
            $packages = WorkPackage::query()->whereBelongsTo($project)->pluck('id', 'code');
            foreach ($plan as $i => [$title, $code, $offset, $done]) {
                if (! isset($packages[$code])) {
                    continue;
                }
                $done = $done && $project->status === 'active';
                $milestone = new Milestone([
                    'title' => $title,
                    'planned_at' => now()->addDays($project->status === 'active' ? $offset : $offset + 300)->toDateString(),
                    'status' => $done ? 'completed' : 'pending',
                    'completed_at' => $done ? now()->addDays($offset + 2)->toDateString() : null,
                    'sort_order' => $i + 1,
                ]);
                $milestone->project_id = $project->id;
                $milestone->work_package_id = $packages[$code];
                $milestone->save();
            }
        }
    }

    private function progress(): void
    {
        if (ConstructionProgressUpdate::query()->exists()) {
            return;
        }

        $service = app(ConstructionService::class);
        foreach ($this->projects()->where('status', 'active') as $project) {
            $packages = WorkPackage::query()->whereBelongsTo($project)->get()->keyBy('code');
            $milestones = Milestone::query()->whereBelongsTo($project)->get()->keyBy('work_package_id');

            $str1 = $packages['WP-STR1'];
            $target = (float) $str1->progress_percent;
            $str1->forceFill(['progress_percent' => 0])->save();

            $steps = [
                [$str1, max(10, $target - 45), 56, '1st floor slab cast.', 'investor', 'approve'],
                [$str1, max(15, $target - 25), 35, '2nd floor columns and slab complete.', 'internal', 'approve'],
                [$str1, $target, 14, '3rd floor slab cast, 4th floor shuttering in progress.', 'investor', 'approve'],
                [$packages['WP-STR2'], (float) $packages['WP-STR2']->progress_percent, 6, '5th floor column rod binding started.', 'internal', 'approve'],
                [$packages['WP-BRK'], (float) $packages['WP-BRK']->progress_percent, 4, 'Brickwork on ground and 1st floor.', 'investor', 'approve'],
                [$packages['WP-STR1'], $target + 20, 2, '4th floor roof shuttering done.', 'internal', 'reject'],
                [$packages['WP-STR1'], $target + 10, 1, '4th floor rod binding 60% complete.', 'internal', 'pending'],
            ];

            foreach ($steps as [$package, $percent, $daysAgo, $notes, $visibility, $outcome]) {
                if ($percent <= 0) {
                    continue;
                }
                $update = $service->submitProgress($project, [
                    'work_package' => $package->fresh(), 'milestone' => $milestones[$package->id] ?? null,
                    'progress_percent' => (string) min(100, $percent), 'report_date' => $this->day($daysAgo),
                    'notes' => $notes, 'visibility' => $visibility,
                ], $this->engineer);

                match ($outcome) {
                    'approve' => $service->approveProgress($update, $this->admin),
                    'reject' => $service->rejectProgress($update, 'Site photos do not match the reported progress.', $this->admin),
                    default => null,
                };
            }
        }
    }

    private function labour(): void
    {
        if (LabourEntry::query()->exists()) {
            return;
        }

        $service = app(ConstructionService::class);
        $crews = [
            ['mason', 'WP-STR1', 12, 900],
            ['helper', 'WP-STR1', 18, 650],
            ['rod_binder', 'WP-STR1', 8, 950],
            ['carpenter', 'WP-STR1', 6, 1000],
            ['rod_binder', 'WP-STR2', 6, 950],
            ['mason', 'WP-BRK', 10, 900],
            ['helper', 'WP-BRK', 12, 650],
            ['welder', 'WP-STR2', 2, 1200],
            ['electrician', null, 2, 1100],
            ['plumber', null, 2, 1050],
            ['other', null, 4, 700],
        ];

        foreach ($this->projects()->where('status', 'active') as $p => $project) {
            $packages = WorkPackage::query()->whereBelongsTo($project)->pluck('id', 'code');
            $n = 0;
            for ($daysAgo = 29; $daysAgo >= 0; $daysAgo--) {
                if (Carbon::parse($this->day($daysAgo))->isFriday()) {
                    continue;
                }
                foreach ($crews as $c => [$type, $code, $headcount, $rate]) {
                    if (($daysAgo + $c + $p) % 3 !== 0) {
                        continue;
                    }
                    $n++;
                    $entry = $service->createLabour($project, [
                        'work_package' => $code ? WorkPackage::query()->find($packages[$code]) : null,
                        'work_date' => $this->day($daysAgo),
                        'labour_type' => $type,
                        'headcount' => max(1, $headcount - $p * 2 + ($daysAgo % 3)),
                        'units' => $type === 'helper' ? '1' : ($daysAgo % 4 === 0 ? '1.5' : '1'),
                        'rate' => (string) $rate,
                        'notes' => $code ? null : 'Site maintenance',
                    ], $this->engineer);

                    if ($daysAgo <= 2) {
                        continue;
                    }
                    if ($n % 17 === 0) {
                        $service->rejectLabour($entry, 'Headcount does not match the attendance sheet.', $this->admin);

                        continue;
                    }
                    $service->approveLabour($entry, $this->admin);
                    if ($n % 23 === 0) {
                        $service->reverseLabour($entry, 'Entered twice for the same crew.', $this->admin);
                    }
                }
            }
        }
    }

    private function stockIssues(): void
    {
        if (StockIssue::query()->exists()) {
            return;
        }

        $service = app(InventoryService::class);
        foreach ($this->projects()->where('status', 'active') as $p => $project) {
            $warehouse = $this->projectWarehouse($project);
            $packages = WorkPackage::query()->whereBelongsTo($project)->get()->keyBy('code');
            $issues = [
                [$packages['WP-STR1'], 25, '1st floor slab casting', [['CEM-OPC-50', 120], ['STL-ROD-12', 180], ['STL-ROD-16', 150]]],
                [$packages['WP-STR1'], 12, '3rd floor slab casting', [['CEM-OPC-50', 100], ['STL-ROD-16', 120]]],
                [$packages['WP-STR2'], 5, '5th floor columns', [['CEM-OPC-50', 40], ['STL-ROD-12', 90]]],
                [$packages['WP-BRK'], 3, 'Ground floor brickwork', [['BRK-1ST', 250 - $p * 50], ['CEM-OPC-50', 30]]],
            ];
            foreach ($issues as $i => [$package, $daysAgo, $purpose, $lines]) {
                $issue = $service->issue($warehouse, $project, $package,
                    array_map(fn ($l) => ['material' => $this->material($l[0]), 'quantity' => (string) $l[1]], $lines),
                    ['issued_at' => $this->day($daysAgo), 'purpose' => $purpose], $this->engineer, (string) Str::uuid());
                if ($p === 0 && $i === 2) {
                    $service->reverseIssue($issue, 'Wrong work package selected.', $this->admin);
                }
            }
        }
    }

    private function transfers(): void
    {
        if (MaterialTransfer::query()->exists()) {
            return;
        }

        $central = Warehouse::query()->whereNull('project_id')->first();
        if (! $central) {
            return;
        }
        $service = app(TransferService::class);
        foreach ($this->projects()->where('status', 'active') as $p => $project) {
            $warehouse = $this->projectWarehouse($project);
            $posted = $service->request($central, $warehouse, [
                ['material' => $this->material('AGG-SAND'), 'quantity' => '400'],
                ['material' => $this->material('AGG-STONE'), 'quantity' => '350'],
            ], 'Slab casting', $this->engineer, (string) Str::uuid());
            $service->approve($posted, $this->admin);
            $service->post($posted, $this->admin);

            $service->request($central, $warehouse, [
                ['material' => $this->material('ELC-WIRE-2'), 'quantity' => (string) (200 + $p * 100)],
            ], 'Electrical conduit work', $this->engineer, (string) Str::uuid());
        }
    }

    private function adjustments(): void
    {
        if (StockAdjustment::query()->exists()) {
            return;
        }

        $service = app(InventoryService::class);
        $project = $this->projects()->firstWhere('status', 'active');
        $warehouse = $this->projectWarehouse($project);

        $approved = $service->requestAdjustment($warehouse, [
            ['material' => $this->material('CEM-OPC-50'), 'qty_delta' => '-8', 'unit_cost' => null],
            ['material' => $this->material('BRK-1ST'), 'qty_delta' => '-40', 'unit_cost' => null],
        ], 'Damaged by rain', $this->engineer, (string) Str::uuid());
        $service->approveAdjustment($approved, $this->admin);

        $service->requestAdjustment($warehouse, [
            ['material' => $this->material('STL-ROD-12'), 'qty_delta' => '15', 'unit_cost' => null],
        ], 'Physical count surplus', $this->engineer, (string) Str::uuid());
    }

    private function borrows(): void
    {
        if (MaterialBorrow::query()->exists()) {
            return;
        }

        $active = $this->projects()->where('status', 'active')->values();
        if ($active->count() < 2) {
            return;
        }
        $service = app(BorrowService::class);
        $source = $this->projectWarehouse($active[0]);
        $destination = $this->projectWarehouse($active[1]);

        $borrow = $service->request($source, $destination, [
            ['material' => $this->material('CEM-OPC-50'), 'quantity' => '60'],
            ['material' => $this->material('STL-ROD-16'), 'quantity' => '100'],
        ], 'Supplier delivery delayed', $this->engineer);
        $service->approve($borrow, [], $this->admin);
        $service->issue($borrow, $this->admin);
        $lines = $borrow->lines()->get();
        $service->return($borrow->fresh(), [$lines[0]->id => '30'], $this->engineer, (string) Str::uuid());

        $service->request($destination, $source, [
            ['material' => $this->material('BRK-1ST'), 'quantity' => '120'],
        ], 'Short on bricks', $this->engineer);
    }

    private function purchaseReturns(): void
    {
        if (PurchaseReturn::query()->exists() || ! $grn = GoodsReceipt::query()->first()) {
            return;
        }

        $line = $grn->lines()->first();
        app(PurchasingService::class)->returnGoods($grn, [$line->id => '12'], 'Bags damaged in transit', $this->admin, (string) Str::uuid());
    }

    private function customerNotes(): void
    {
        $notes = [
            'Interested in a south-facing 3 bed unit.',
            'Visited the site with family.',
            'Asked for the payment schedule.',
            'Prefers an upper floor.',
            'Follow up after Eid.',
        ];
        Customer::query()->whereDoesntHave('notes')->orderBy('id')->get()->each(function (Customer $customer, int $i) use ($notes) {
            foreach ([0, 2] as $k) {
                $customer->notes()->create(['body' => $notes[($i + $k) % count($notes)], 'visibility' => 'internal', 'created_by' => $this->admin->id]);
            }
        });
    }
}
