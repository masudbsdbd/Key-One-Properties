<?php

namespace App\Console\Commands;

use App\Modules\Accounting\Services\EventPoster;
use Illuminate\Console\Command;

class PostLedger extends Command
{
    protected $signature = 'keyone:post-ledger {--limit= : Stop after this many events}';

    protected $description = 'Post pending and failed financial events to the ledger';

    public function handle(EventPoster $poster): int
    {
        $result = $poster->postBacklog($this->option('limit') ? (int) $this->option('limit') : null);
        $this->info("Posted {$result['posted']}, failed {$result['failed']}.");

        return self::SUCCESS;
    }
}
