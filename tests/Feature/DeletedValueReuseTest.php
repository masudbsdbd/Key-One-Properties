<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;
use Illuminate\Database\UniqueConstraintViolationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Deleting a user, material or work package frees its email, mobile, SKU or code; live rows stay unique. */
class DeletedValueReuseTest extends TestCase
{
    public function test_deleted_users_free_their_email_and_mobile(): void
    {
        $admin = $this->superAdmin();
        $payload = ['name' => 'Kamal Uddin', 'email' => 'kamal@example.com', 'mobile' => '01819876543', 'role' => Role::findByName('Sales Executive')->public_id,
            'status' => 'active', 'password' => 'Secret1234', 'password_confirmation' => 'Secret1234'];

        $this->actingAs($admin)->ajax()->post(route('users.store'), $payload)->assertOk();
        $this->actingAs($admin)->ajax()->post(route('users.store'), $payload)->assertStatus(422)->assertJsonValidationErrors(['email', 'mobile']);

        $first = User::query()->where('email', 'kamal@example.com')->firstOrFail();
        $this->actingAs($admin)->ajax()->delete(route('users.destroy', $first))->assertOk();
        $this->actingAs($admin)->ajax()->post(route('users.store'), $payload)->assertOk();
        $this->assertSame(2, User::withTrashed()->where('email', 'kamal@example.com')->count());

        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['email' => 'kamal@example.com']);
    }

    public function test_deleted_materials_and_work_packages_free_their_sku_and_code(): void
    {
        $manager = $this->userWith(['materials.view', 'materials.manage', 'projects.view_all', 'construction.view', 'construction.manage']);
        $category = MaterialCategory::query()->create(['code' => 'CEM', 'name' => 'Cement']);
        $unit = MaterialUnit::query()->create(['code' => 'BAG', 'name' => 'Bag', 'precision' => 0]);
        $material = ['sku' => 'cem-50', 'name' => 'Portland', 'category' => $category->public_id, 'unit' => $unit->public_id, 'is_active' => 1];

        $this->actingAs($manager)->ajax()->post(route('materials.store'), $material)->assertOk();
        $this->actingAs($manager)->ajax()->post(route('materials.store'), $material)->assertStatus(422)->assertJsonValidationErrors('sku');
        $this->actingAs($manager)->ajax()->delete(route('materials.destroy', Material::query()->firstOrFail()))->assertOk();
        $this->actingAs($manager)->ajax()->post(route('materials.store'), $material)->assertOk();

        $project = Project::factory()->create();
        $package = ['code' => 'wp-01', 'name' => 'Piling', 'budget_amount' => '1000', 'status' => 'not_started'];
        $this->actingAs($manager)->ajax()->post(route('construction.work-packages.store', $project), $package)->assertOk();
        $this->actingAs($manager)->ajax()->post(route('construction.work-packages.store', $project), $package)->assertStatus(422)->assertJsonValidationErrors('code');
        $this->actingAs($manager)->ajax()->delete(route('construction.work-packages.destroy', [$project, WorkPackage::query()->firstOrFail()]))->assertOk();
        $this->actingAs($manager)->ajax()->post(route('construction.work-packages.store', $project), $package)->assertOk();
    }

    public function test_an_archived_projects_code_stays_reserved_with_a_clear_message(): void
    {
        $admin = $this->superAdmin();
        $project = Project::factory()->create(['code' => 'LAKE-1', 'name' => 'Lake View']);
        $project->delete();

        $this->actingAs($admin)->ajax()->post(route('projects.store'), ['name' => 'New', 'code' => 'LAKE-1', 'location_text' => 'Dhaka', 'start_date' => '2026-01-01', 'status' => 'active'])
            ->assertStatus(422)->assertJsonPath('errors.code.0', 'Used by the archived project Lake View. Restore it or choose another code.');
    }
}
