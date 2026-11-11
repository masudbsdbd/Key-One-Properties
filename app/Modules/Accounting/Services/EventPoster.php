<?php

namespace App\Modules\Accounting\Services;

use App\Models\FinancialEvent;
use App\Models\JournalEntry;
use App\Models\User;
use App\Modules\Accounting\Rules\PostingRules;
use App\Modules\Accounting\Support\AccountingSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Posts financial events to the ledger, each exactly once (the journal's source key is the event's uuid).
 * A failure never blocks the business action: the event is marked failed with the reason and can be retried.
 */
class EventPoster
{
    public function __construct(private Ledger $ledger, private PostingRules $rules) {}

    public function attempt(FinancialEvent $event): ?JournalEntry
    {
        if (! AccountingSettings::covers($event->effective_at)) {
            return null;
        }

        try {
            // A nested transaction: a failed posting rolls back to here and leaves the caller's work intact.
            $journal = DB::transaction(fn () => $this->post($event));
            $event->forceFill(['processing_status' => FinancialEvent::STATUS_PROCESSED, 'processed_at' => now(), 'last_error' => null, 'attempts' => $event->attempts + 1])->save();

            return $journal;
        } catch (Throwable $e) {
            $event->forceFill(['processing_status' => FinancialEvent::STATUS_FAILED, 'last_error' => Str::limit($e->getMessage(), 500), 'attempts' => $event->attempts + 1])->save();

            return null;
        }
    }

    /**
     * Post every pending or failed event since the start of the books, oldest first.
     *
     * @return array{posted: int, failed: int}
     */
    public function postBacklog(?int $limit = null): array
    {
        $result = ['posted' => 0, 'failed' => 0];
        if (! AccountingSettings::enabled()) {
            return $result;
        }
        self::backlog()->orderBy('id')->when($limit, fn ($q) => $q->limit($limit))->get()->each(function (FinancialEvent $event) use (&$result) {
            $this->attempt($event);
            $result[$event->processing_status === FinancialEvent::STATUS_FAILED ? 'failed' : 'posted']++;
        });

        return $result;
    }

    /** Events the ledger has not taken yet. */
    public static function backlog()
    {
        $start = AccountingSettings::startDate()?->toDateString() ?? '9999-12-31';

        return FinancialEvent::query()->whereIn('processing_status', [FinancialEvent::STATUS_PENDING, FinancialEvent::STATUS_FAILED])->where('effective_at', '>=', $start);
    }

    private function post(FinancialEvent $event): ?JournalEntry
    {
        $user = $event->recorded_by ? User::query()->find($event->recorded_by) : null;

        // A cancelled document: reverse the journal its original event produced, if it produced one.
        if ($event->reversal_of_id) {
            $original = JournalEntry::query()->where('financial_event_id', $event->reversal_of_id)->where('status', 'posted')->whereNull('reversal_of_id')->first();
            if (! $original || $original->isReversed()) {
                return null;
            }

            return $this->ledger->reverse($original, $event->effective_at->max($original->accounting_date), 'Cancelled', $user, $event);
        }

        $posting = $this->rules->for($event);
        if ($posting === null) {
            return null;
        }

        return $this->ledger->post([
            'type' => 'system', 'date' => $event->effective_at, 'narration' => $posting['narration'], 'source_key' => 'event:'.$event->event_uuid,
            'event' => $event, 'source' => $event->source, 'project_id' => $event->project_id,
        ], $posting['lines'], $user);
    }
}
