<?php

namespace App\Modules\Construction\Services;

use App\Models\ConstructionProgressUpdate;
use App\Models\LabourEntry;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;
use App\Support\Costing\CostAttribution;
use App\Support\DocumentNumber;
use App\Support\FinancialEvents\FinancialEventRecorder;
use App\Support\Money;
use App\Support\Workflow\Approval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConstructionService
{
    public function __construct(private CostAttribution $costs, private FinancialEventRecorder $events) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitProgress(Project $project, array $data, User $user): ConstructionProgressUpdate
    {
        $update = new ConstructionProgressUpdate([
            'work_package_id' => $data['work_package']?->id,
            'milestone_id' => $data['milestone']?->id,
            'progress_percent' => Money::amount($data['progress_percent']),
            'report_date' => $data['report_date'],
            'notes' => $data['notes'],
            'visibility' => $data['visibility'],
            'submitted_by' => $user->id,
        ]);
        $update->project_id = $project->id;
        $update->previous_percent = $data['work_package']?->progress_percent;
        $update->status = 'submitted';
        $update->save();

        $this->log($project, $user, $update, 'progress_submitted', ['progress' => (float) $update->progress_percent]);

        return $update;
    }

    public function approveProgress(ConstructionProgressUpdate $update, User $user): void
    {
        DB::transaction(function () use ($update, $user) {
            $update = ConstructionProgressUpdate::query()->lockForUpdate()->findOrFail($update->id);
            Approval::ensureStatus($update->status, 'submitted', 'This update is no longer awaiting approval.');
            Approval::ensureNotSelf($user, $update->submitted_by, 'a progress update');

            $update->forceFill([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'published_at' => $update->visibility === 'investor' ? now() : null,
            ])->save();

            if ($update->work_package_id) {
                $package = WorkPackage::query()->lockForUpdate()->findOrFail($update->work_package_id);
                $package->progress_percent = $update->progress_percent;
                $package->status = Money::cmp($update->progress_percent, '100') >= 0 ? 'completed'
                    : (Money::isPositive($update->progress_percent) && $package->status === 'not_started' ? 'in_progress' : $package->status);
                $package->save();
            }

            if ($update->milestone_id && Money::cmp($update->progress_percent, '100') >= 0) {
                Milestone::query()->whereKey($update->milestone_id)->where('status', 'pending')
                    ->update(['status' => 'completed', 'completed_at' => $update->report_date]);
            }

            $this->log($update->project, $user, $update, 'progress_approved', ['progress' => (float) $update->progress_percent]);
        });
    }

    public function rejectProgress(ConstructionProgressUpdate $update, string $reason, User $user): void
    {
        DB::transaction(function () use ($update, $reason, $user) {
            $update = ConstructionProgressUpdate::query()->lockForUpdate()->findOrFail($update->id);
            Approval::ensureStatus($update->status, 'submitted', 'This update is no longer awaiting approval.');
            $update->forceFill(['status' => 'rejected', 'rejection_reason' => $reason, 'approved_by' => $user->id, 'approved_at' => now()])->save();
            $this->log($update->project, $user, $update, 'progress_rejected', ['reason' => $reason]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createLabour(Project $project, array $data, User $user): LabourEntry
    {
        return DB::transaction(function () use ($project, $data, $user) {
            $units = Money::qty($data['units'] ?? '1');
            $entry = new LabourEntry([
                'work_package_id' => $data['work_package']?->id,
                'work_date' => $data['work_date'],
                'labour_type' => $data['labour_type'],
                'headcount' => (int) $data['headcount'],
                'units' => $units,
                'rate' => Money::rate($data['rate']),
                'notes' => $data['notes'] ?? null,
            ]);
            $entry->project_id = $project->id;
            $entry->entry_no = DocumentNumber::next('LAB');
            $entry->total_amount = self::labourTotal((int) $data['headcount'], $units, $data['rate']);
            $entry->status = 'submitted';
            $entry->created_by = $user->id;
            $entry->save();

            return $entry;
        });
    }

    /**
     * @param  array<int, array{type: string, headcount: int|string, units: string, rate: string}>  $rows
     * @return Collection<int, LabourEntry>
     */
    public function createLabourSheet(Project $project, array $header, array $rows, User $user): Collection
    {
        return DB::transaction(fn () => collect($rows)->map(fn (array $row) => $this->createLabour($project, [
            'work_package' => $header['work_package'] ?? null, 'work_date' => $header['work_date'], 'notes' => $header['notes'] ?? null,
            'labour_type' => $row['type'], 'headcount' => $row['headcount'], 'units' => $row['units'], 'rate' => $row['rate'],
        ], $user)));
    }

    public static function labourTotal(int $headcount, string $units, string $rate): string
    {
        return Money::mul(Money::mul((string) $headcount, $units, 3), $rate, 2);
    }

    public function approveLabour(LabourEntry $entry, User $user): void
    {
        DB::transaction(function () use ($entry, $user) {
            $entry = LabourEntry::query()->lockForUpdate()->findOrFail($entry->id);
            Approval::ensureStatus($entry->status, 'submitted', 'This entry is no longer awaiting approval.');
            Approval::ensureNotSelf($user, $entry->created_by, 'a labour entry');

            $entry->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now(), 'version' => $entry->version + 1])->save();

            $this->costs->post($entry->project, $entry, 'labour', $entry->total_amount, $entry->work_date, $entry->work_package_id);
            $this->events->record('construction', $entry, 'labour.approved', $entry->total_amount, $entry->work_date,
                ['entry_no' => $entry->entry_no, 'labour_type' => $entry->labour_type], project: $entry->project, user: $user);
        });
    }

    public function rejectLabour(LabourEntry $entry, string $reason, User $user): void
    {
        DB::transaction(function () use ($entry, $reason, $user) {
            $entry = LabourEntry::query()->lockForUpdate()->findOrFail($entry->id);
            Approval::ensureStatus($entry->status, 'submitted', 'This entry is no longer awaiting approval.');
            $entry->forceFill(['status' => 'rejected', 'rejection_reason' => $reason, 'approved_by' => $user->id, 'approved_at' => now()])->save();
        });
    }

    public function reverseLabour(LabourEntry $entry, string $reason, User $user): void
    {
        DB::transaction(function () use ($entry, $reason, $user) {
            $entry = LabourEntry::query()->lockForUpdate()->findOrFail($entry->id);
            Approval::ensureStatus($entry->status, 'approved', 'Only approved entries can be reversed.');

            $entry->forceFill(['status' => 'reversed', 'reversed_by' => $user->id, 'reversed_at' => now(), 'reversal_reason' => $reason, 'version' => $entry->version + 1])->save();
            $this->costs->reverse($entry, now(config('app.display_timezone')));
            $this->events->reverse('construction', $entry, now(config('app.display_timezone')), $user);
        });
    }

    private function log(Project $project, User $user, $subject, string $event, array $attributes = []): void
    {
        activity('construction')->event($event)->causedBy($user)->performedOn($subject)
            ->tap(fn ($a) => $a->project_id = $project->id)
            ->withProperties(['attributes' => $attributes])->log($event);
    }
}
