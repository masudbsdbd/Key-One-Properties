<?php

namespace App\Console\Commands;

use App\Modules\Inventory\Services\StockReconciler;
use App\Support\Reconciliation\SubledgerReconciler;
use Illuminate\Console\Command;

class Reconcile extends Command
{
    protected $signature = 'keyone:reconcile';

    protected $description = 'Check stock, supplier and contractor ledgers and project cost against their source documents';

    public function handle(StockReconciler $stock, SubledgerReconciler $subledgers): int
    {
        $stockDiffs = $stock->differences();
        $this->line($stockDiffs ? '<error>Stock: '.count($stockDiffs).' difference(s). Run keyone:reconcile-stock for details.</error>' : '<info>Stock balances match the movement ledger.</info>');

        $diffs = $subledgers->differences();
        foreach (['Supplier ledger', 'Contractor ledger', 'Project cost', 'Customer statement', 'Investor ledger'] as $check) {
            $count = count(array_filter($diffs, fn ($d) => $d['check'] === $check));
            $this->line($count ? "<error>{$check}: {$count} difference(s).</error>" : "<info>{$check} matches its source documents.</info>");
        }
        if ($diffs) {
            $this->table(['Check', 'Document', 'Expected', 'Recorded'], array_map(fn ($d) => array_values($d), $diffs));
        }

        return $stockDiffs || $diffs ? self::FAILURE : self::SUCCESS;
    }
}
