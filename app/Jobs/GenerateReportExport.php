<?php

namespace App\Jobs;

use App\Models\ReportRun;
use App\Modules\Reports\ReportRegistry;
use App\Support\Export\Report;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Builds a queued report export as the requesting user, with that user's permissions and project scope
 * at the time the job runs, and stores it on the private disk.
 */
class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $runId) {}

    public function handle(): void
    {
        $run = ReportRun::query()->with('requester')->find($this->runId);
        if (! $run || $run->status !== 'queued') {
            return;
        }
        $run->forceFill(['status' => 'running', 'started_at' => now()])->save();
        $report = ReportRegistry::find($run->report_key);
        $user = $run->requester;
        if (! $report || ! $user->isActive() || ! $report->allowed($user) || ! $user->can('reports.export')) {
            $run->forceFill(['status' => 'failed', 'error_summary' => 'Not allowed to run this report.', 'completed_at' => now()])->save();

            return;
        }

        $request = Request::create('/', 'GET', $run->filters_json);
        $query = $report->query($request, $user);
        $export = Report::make($report->name())->meta($report->describe($request, $user))->columns($report->columns())
            ->rows(fn () => (clone $query)->lazy()->map(fn ($r) => $report->row($r)))->totals($report->totals(clone $query) ?? []);
        $disk = Storage::disk('local');
        $path = 'report-exports/'.Str::random(40).'.'.$run->format;

        try {
            if ($run->format === 'pdf') {
                [$bytes, $rows] = $export->pdfBytes();
                $disk->put($path, $bytes);
            } else {
                $stream = fopen('php://temp', 'w+');
                $rows = $export->writeCsv($stream);
                rewind($stream);
                $disk->put($path, $stream);
                fclose($stream);
            }
        } catch (HttpException $e) {
            $run->forceFill(['status' => 'failed', 'error_summary' => $e->getMessage(), 'completed_at' => now()])->save();

            return;
        } catch (Throwable $e) {
            report($e);
            $run->forceFill(['status' => 'failed', 'error_summary' => 'The export could not be built.', 'completed_at' => now()])->save();

            return;
        }

        $run->file()->create([
            'storage_disk' => 'local', 'storage_path' => $path, 'file_name' => $export->filename($run->format),
            'mime_type' => $run->format === 'pdf' ? 'application/pdf' : 'text/csv', 'size_bytes' => $disk->size($path),
        ]);
        $run->forceFill(['status' => 'completed', 'completed_at' => now(), 'row_count' => $rows, 'expires_at' => now()->addDays((int) config('keyone.report_export_days', 7))])->save();
    }
}
