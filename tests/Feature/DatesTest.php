<?php

namespace Tests\Feature;

use App\Models\InstallmentScheduleItem;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DatesTest extends TestCase
{
    public function test_today_is_the_local_date_so_day_counts_are_whole(): void
    {
        // 01:30 in Dhaka is still the previous day in UTC.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 01:30', 'Asia/Dhaka'));
        config(['app.display_timezone' => 'Asia/Dhaka']);

        $today = Dates::today();
        $this->assertSame('2026-11-10', $today->toDateString());
        $this->assertSame(9, (int) CarbonImmutable::parse('2026-11-01')->diffInDays($today));
        $this->assertTrue(CarbonImmutable::parse('2026-11-17')->between($today, $today->addDays(7)));

        $item = new InstallmentScheduleItem(['due_date' => '2026-11-10', 'principal_due' => '100', 'fee_due' => '0', 'principal_paid' => '0', 'fee_paid' => '0', 'status' => 'upcoming']);
        $item->refreshStatus();
        $this->assertSame('upcoming', $item->status);

        CarbonImmutable::setTestNow();
    }
}
