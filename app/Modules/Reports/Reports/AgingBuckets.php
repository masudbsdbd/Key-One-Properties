<?php

namespace App\Modules\Reports\Reports;

final class AgingBuckets
{
    public static function label(int $days): string
    {
        return match (true) {
            $days <= 0 => 'Not due',
            $days <= 30 => '1–30 days',
            $days <= 60 => '31–60 days',
            $days <= 90 => '61–90 days',
            default => 'Over 90 days',
        };
    }
}
