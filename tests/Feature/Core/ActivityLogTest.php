<?php

namespace Tests\Feature\Core;

use App\Models\User;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    public function test_requires_permission(): void
    {
        $this->actingAs($this->userWith(['dashboard.view']))->get(route('activity.index'))->assertForbidden();
        $this->actingAs($this->userWith(['dashboard.view']))->ajax()->get(route('activity.table'))->assertForbidden();
    }

    public function test_lists_changes_with_before_and_after_and_filters_by_module(): void
    {
        $user = User::factory()->create(['name' => 'Before Name']);
        $user->update(['name' => 'After Name']);

        $html = $this->actingAs($this->userWith(['activity.view']))->ajax()
            ->get(route('activity.table', ['module' => 'users']))
            ->assertOk()->json('data.html');

        $this->assertStringContainsString('Before Name', $html);
        $this->assertStringContainsString('After Name', $html);
    }

    public function test_sensitive_fields_are_redacted(): void
    {
        $user = User::factory()->create();
        activity('security')->performedOn($user)->withProperties(['attributes' => ['password' => 'plain-secret']])->log('test');

        $html = $this->actingAs($this->userWith(['activity.view']))->ajax()->get(route('activity.table'))->json('data.html');

        $this->assertStringNotContainsString('plain-secret', $html);
    }
}
