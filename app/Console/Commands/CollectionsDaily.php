<?php

namespace App\Console\Commands;

use App\Modules\Collections\Services\LateFeeService;
use Illuminate\Console\Command;

class CollectionsDaily extends Command
{
    protected $signature = 'keyone:collections-daily';

    protected $description = 'Mark overdue installments and charge late fees where a customer policy applies';

    public function handle(LateFeeService $fees): int
    {
        $result = $fees->runDaily(now(config('app.display_timezone')));
        $this->info("Checked {$result['plans']} plan(s); {$result['charges']} late fee(s) charged.");

        return self::SUCCESS;
    }
}
