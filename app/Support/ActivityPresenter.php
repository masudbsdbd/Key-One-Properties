<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivityPresenter
{
    private const REDACTED = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'nid_or_passport', 'token'];

    /** Document number columns; a record that has one is named by it, e.g. "Booking BK-2026-00004". */
    private const NUMBERS = ['booking_no', 'sale_no', 'po_no', 'wo_no', 'bill_no', 'payment_no', 'expense_no', 'issue_no', 'transfer_no', 'borrow_no', 'plan_no',
        'agreement_no', 'entry_no', 'receipt_no', 'return_no', 'refund_no', 'run_no', 'calculation_no', 'adjustment_no', 'reschedule_no', 'variation_no', 'transaction_no',
        'distribution_no', 'revision_no', 'grn_no'];

    /** Class names that read better under the word the screens use. */
    private const TYPES = ['Flat' => 'Unit', 'FlatBooking' => 'Booking', 'FlatSale' => 'Sale', 'LabourEntry' => 'Labour', 'ContractorWorkOrder' => 'Work Order',
        'MaterialTransfer' => 'Transfer', 'MaterialBorrow' => 'Borrow', 'CustomerPayment' => 'Customer Payment', 'InstallmentPlan' => 'Installment Plan'];

    public static function type(?string $class): string
    {
        $base = class_basename((string) $class);

        return self::TYPES[$base] ?? Str::headline($base ?: 'record');
    }

    public static function subject(Activity $activity): string
    {
        $subject = $activity->subject;
        if ($subject) {
            $type = self::type($subject::class);
            foreach (self::NUMBERS as $field) {
                if (! empty($subject->{$field})) {
                    return $type.' '.$subject->{$field};
                }
            }
            if (! empty($subject->flat_no)) {
                return $type.' '.$subject->flat_no;
            }

            return $subject->name ?? $subject->full_name ?? $subject->title ?? $subject->original_name ?? $subject->label ?? $type;
        }

        return $activity->properties['name'] ?? ($activity->subject_type ? self::type($activity->subject_type) : '—');
    }

    public static function event(Activity $activity): string
    {
        return Str::headline((string) ($activity->event ?: $activity->description));
    }

    /**
     * Icon and tone for the timeline dot.
     *
     * @return array{icon: string, tone: string}
     */
    public static function style(Activity $activity): array
    {
        $event = (string) ($activity->event ?: $activity->description);

        return match (true) {
            $event === 'created', str_ends_with($event, '_added'), str_ends_with($event, '_uploaded'), $event === 'bulk_created' => ['icon' => 'plus', 'tone' => 'success'],
            $event === 'deleted', str_ends_with($event, '_removed'), str_ends_with($event, '_deleted') => ['icon' => 'trash', 'tone' => 'danger'],
            str_contains($event, 'reject'), str_contains($event, 'revers'), str_contains($event, 'failed'), str_contains($event, 'locked') => ['icon' => 'alert', 'tone' => 'danger'],
            str_contains($event, 'approved'), $event === 'restored' => ['icon' => 'check', 'tone' => 'success'],
            str_contains($event, 'progress') => ['icon' => 'bar-chart', 'tone' => 'accent'],
            str_contains($event, 'reorder') => ['icon' => 'list', 'tone' => 'info'],
            str_contains($event, 'member'), str_contains($event, 'primary') => ['icon' => 'users', 'tone' => 'info'],
            str_contains($event, 'login'), str_contains($event, 'password'), str_contains($event, 'two_factor'), str_contains($event, 'identity') => ['icon' => 'lock', 'tone' => 'warning'],
            str_contains($event, 'document'), str_contains($event, 'attachment') => ['icon' => 'file', 'tone' => 'info'],
            default => ['icon' => 'edit', 'tone' => 'accent'],
        };
    }

    /**
     * Collapse runs of the same creation (same person, same kind of record, same minute) into one entry.
     *
     * @param  iterable<int, Activity>  $entries
     * @return array<int, array{entry: Activity, count: int, subjects: array<int, string>}>
     */
    public static function group(iterable $entries): array
    {
        $groups = [];
        foreach ($entries as $entry) {
            $last = $groups[array_key_last($groups) ?? -1] ?? null;
            $sameRun = $last && $entry->event === 'created' && $last['entry']->event === 'created'
                && $last['entry']->subject_type === $entry->subject_type && $last['entry']->causer_id === $entry->causer_id
                && $last['entry']->created_at->format('Y-m-d H:i') === $entry->created_at->format('Y-m-d H:i');

            if ($sameRun) {
                $groups[array_key_last($groups)]['count']++;
                $groups[array_key_last($groups)]['subjects'][] = self::subject($entry);
            } else {
                $groups[] = ['entry' => $entry, 'count' => 1, 'subjects' => [self::subject($entry)]];
            }
        }

        return $groups;
    }

    public static function isCreation(Activity $activity): bool
    {
        return in_array($activity->event, ['created', 'members_added', 'bulk_created', 'media_uploaded'], true) || empty($activity->properties['old']);
    }

    /**
     * Changes for display: on creation only the new values are shown.
     *
     * @return array<int, array{field: string, old: ?string, new: string}>
     */
    public static function displayChanges(Activity $activity): array
    {
        $creation = self::isCreation($activity);

        return array_map(fn (array $c) => [
            'field' => $c['field'],
            'old' => $creation || $c['old'] === '—' ? null : $c['old'],
            'new' => $c['new'],
        ], self::changes($activity));
    }

    /**
     * @return array<int, array{field: string, old: string, new: string}>
     */
    public static function changes(Activity $activity): array
    {
        $old = (array) ($activity->properties['old'] ?? []);
        $new = (array) ($activity->properties['attributes'] ?? []);
        $fields = array_unique(array_merge(array_keys($old), array_keys($new)));

        return collect($fields)->map(fn (string $field) => [
            'field' => Str::headline($field),
            'old' => self::format($field, $old[$field] ?? null),
            'new' => self::format($field, $new[$field] ?? null),
        ])->reject(fn (array $change) => $change['old'] === $change['new'])->values()->all();
    }

    /**
     * @return array<string, string>
     */
    public static function context(Activity $activity): array
    {
        return collect($activity->properties->except(['old', 'attributes', 'name'])->all())
            ->map(fn ($value, $key) => self::format((string) $key, $value))
            ->mapWithKeys(fn ($value, $key) => [Str::headline((string) $key) => $value])
            ->all();
    }

    private static function format(string $field, mixed $value): string
    {
        // Values of these fields are never shown, only whether they changed.
        if (in_array(Str::snake($field), self::REDACTED, true)) {
            return $value === null ? '—' : '••••••';
        }
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => is_scalar($v) ? self::format('', $v) : json_encode($v), $value)) ?: '—';
        }

        $string = (string) $value;

        // ISO timestamps and plain dates read as "08 Nov 2026".
        if (preg_match('/^\d{4}-\d{2}-\d{2}(T[\d:.]+Z?|\s[\d:]+)?$/', $string)) {
            try {
                $date = Carbon::parse($string);

                return strlen($string) > 10 && $date->format('H:i:s') !== '00:00:00'
                    ? $date->timezone(config('app.display_timezone'))->format('d M Y, h:i A')
                    : $date->format('d M Y');
            } catch (\Throwable) {
            }
        }

        // Machine values such as not_started (or a single-word status) read as "Not Started".
        if (preg_match('/^[a-z]+(_[a-z]+)+$/', $string) || (preg_match('/(status|visibility|type|scope|kind)$/', Str::snake($field)) && preg_match('/^[a-z]+$/', $string))) {
            return Str::headline($string);
        }

        // Money and percentage fields read the way the rest of the app shows them.
        if (is_numeric($string) && preg_match('/(^|_)(price|amount|value|cost|budget|total|paid|due|balance|rate)(_|$)/', Str::snake($field))) {
            return Format::money($string);
        }
        if (is_numeric($string) && str_ends_with(Str::snake($field), 'percent')) {
            return rtrim(rtrim(number_format((float) $string, 2, '.', ''), '0'), '.').'%';
        }

        // 30.00 reads as 30, 1450.50 stays as is.
        if (is_numeric($string) && str_contains($string, '.')) {
            return rtrim(rtrim($string, '0'), '.');
        }

        return Str::limit($string, 200);
    }
}
