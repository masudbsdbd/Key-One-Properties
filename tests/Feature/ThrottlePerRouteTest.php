<?php

namespace Tests\Feature;

use Tests\TestCase;

class ThrottlePerRouteTest extends TestCase
{
    public function test_plain_throttles_count_per_route(): void
    {
        $user = $this->superAdmin();
        for ($i = 0; $i < 25; $i++) {
            $this->actingAs($user)->ajax()->get(route('options.projects'))->assertOk();
        }

        $this->actingAs($user)->ajax()->get(route('reports.export', ['key' => 'unit-status', 'format' => 'csv']))->assertOk();
        for ($i = 0; $i < 19; $i++) {
            $this->actingAs($user)->get(route('reports.export', ['key' => 'unit-status', 'format' => 'csv']));
        }
        $this->actingAs($user)->get(route('reports.export', ['key' => 'unit-status', 'format' => 'csv']))->assertStatus(429);
        $this->actingAs($user)->ajax()->get(route('options.projects'))->assertOk();
    }
}
