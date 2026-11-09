<?php

namespace App\Console\Commands;

use App\Models\ReportRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneReportExports extends Command
{
    protected $signature = 'keyone:prune-report-exports';

    protected $description = 'Delete expired report export files';

    public function handle(): int
    {
        $count = 0;
        ReportRun::query()->where('status', 'completed')->where('expires_at', '<=', now())->with('file')->each(function (ReportRun $run) use (&$count) {
            if ($run->file) {
                Storage::disk($run->file->storage_disk)->delete($run->file->storage_path);
                $run->file->delete();
            }
            $run->forceFill(['status' => 'expired'])->save();
            $count++;
        });
        $this->info("{$count} expired export(s) removed.");

        return self::SUCCESS;
    }
}
