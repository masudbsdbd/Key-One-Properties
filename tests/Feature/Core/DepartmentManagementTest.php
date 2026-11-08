<?php

namespace Tests\Feature\Core;

use App\Models\Department;
use App\Models\User;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    public function test_permission_required(): void
    {
        $actor = $this->userWith(['departments.view']);
        $department = Department::create(['name' => 'Sales', 'status' => 'active']);

        $this->actingAs($actor)->get(route('departments.index'))->assertOk();
        $this->actingAs($actor)->ajax()->post(route('departments.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->actingAs($actor)->ajax()->delete(route('departments.destroy', $department))->assertForbidden();
    }

    public function test_create_department_with_members(): void
    {
        $actor = $this->userWith(['departments.view', 'departments.manage']);
        $member = User::factory()->create();

        $this->actingAs($actor)->ajax()->post(route('departments.store'), [
            'name' => 'Engineering', 'status' => 'active', 'members' => [$member->public_id],
        ])->assertOk();

        $this->assertTrue(Department::query()->where('name', 'Engineering')->firstOrFail()->users->contains($member));
    }

    public function test_user_options_return_public_ids_only(): void
    {
        $actor = $this->userWith(['departments.manage']);
        $member = User::factory()->create(['name' => 'Robin Jamil']);

        $json = $this->actingAs($actor)->ajax()->get(route('options.users', ['q' => 'Robin']))->assertOk()->json();

        $this->assertSame($member->public_id, $json['results'][0]['id']);
        $this->assertArrayHasKey('more', $json['pagination']);
    }

    public function test_user_options_forbidden_without_related_permission(): void
    {
        $this->actingAs($this->userWith(['dashboard.view']))->ajax()->get(route('options.users'))->assertForbidden();
    }
}
