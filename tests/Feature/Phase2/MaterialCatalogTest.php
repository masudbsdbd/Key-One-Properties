<?php

namespace Tests\Feature\Phase2;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialUnit;
use Tests\TestCase;

class MaterialCatalogTest extends TestCase
{
    public function test_categories_and_units_can_be_edited_and_deleted_only_when_unused(): void
    {
        $manager = $this->userWith(['materials.view', 'materials.manage']);
        $category = MaterialCategory::query()->create(['code' => 'CEM', 'name' => 'Cement']);
        $spare = MaterialCategory::query()->create(['code' => 'TMP', 'name' => 'Temporary']);
        $unit = MaterialUnit::query()->create(['code' => 'BAG', 'name' => 'Bag', 'precision' => 0]);
        $material = new Material(['sku' => 'CEM-1', 'name' => 'Portland']);
        $material->forceFill(['category_id' => $category->id, 'unit_id' => $unit->id])->save();

        $this->actingAs($manager)->ajax()->get(route('materials.categories.edit', $category))->assertOk()->assertJsonPath('data.title', 'Edit Category');
        $this->actingAs($manager)->ajax()->put(route('materials.categories.update', $category), ['code' => 'cem2', 'name' => 'Cement & Lime'])->assertOk();
        $this->assertSame(['CEM2', 'Cement & Lime'], [$category->fresh()->code, $category->fresh()->name]);
        $this->actingAs($manager)->ajax()->put(route('materials.categories.update', $spare), ['code' => 'CEM2', 'name' => 'Clash'])->assertStatus(422);
        $this->actingAs($manager)->ajax()->put(route('materials.units.update', $unit), ['code' => 'BAG', 'name' => 'Bag (50 kg)', 'precision' => 0])->assertOk();

        $this->actingAs($manager)->ajax()->delete(route('materials.categories.destroy', $category))->assertStatus(422);
        $this->actingAs($manager)->ajax()->delete(route('materials.units.destroy', $unit))->assertStatus(422);
        $this->actingAs($manager)->ajax()->delete(route('materials.categories.destroy', $spare))->assertOk();
        $this->assertModelMissing($spare);

        $viewer = $this->userWith(['materials.view']);
        $this->actingAs($viewer)->ajax()->put(route('materials.categories.update', $category), ['code' => 'X', 'name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->ajax()->delete(route('materials.units.destroy', $unit))->assertForbidden();
        $this->actingAs($viewer)->ajax()->get(route('materials.catalog'))->assertOk()->assertDontSee('aria-label="Edit', false);
    }
}
