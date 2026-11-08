<?php

namespace App\Support;

class Phone
{
    /**
     * Normalise Bangladeshi mobile numbers to 01XXXXXXXXX; other numbers keep their digits
     * (with a leading + when given in international form).
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            return '0'.substr($digits, 3);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            return '0'.$digits;
        }

        return str_starts_with(trim($value), '+') ? '+'.$digits : $digits;
    }

    public static function display(?string $value): string
    {
        if (! $value) {
            return '—';
        }

        return preg_match('/^01\d{9}$/', $value) ? '+880 '.substr($value, 1, 4).' '.substr($value, 5, 3).' '.substr($value, 8) : $value;
    }
}
