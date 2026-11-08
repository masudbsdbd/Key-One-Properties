<?php

namespace App\Support;

use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivityPresenter
{
    private const REDACTED = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'nid_or_passport', 'token'];

    public static function subject(Activity $activity): string
    {
        $subject = $activity->subject;
        if ($subject) {
            return $subject->name ?? $subject->full_name ?? $subject->title ?? $subject->flat_no ?? $subject->original_name ?? Str::headline(class_basename($subject));
        }

        return $activity->properties['name'] ?? ($activity->subject_type ? Str::headline(class_basename($activity->subject_type)) : '—');
    }

    public static function event(Activity $activity): string
    {
        return Str::headline((string) ($activity->event ?: $activity->description));
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
            return implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $value)) ?: '—';
        }

        return Str::limit((string) $value, 200);
    }
}
